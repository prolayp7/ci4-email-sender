<?php

namespace App\Services;

class TagService
{
    /** @return list<array{id:int,name:string}> All tags, alphabetical -- used to populate the filter dropdown. */
    public function all(): array
    {
        return db_connect()->table('tags')->orderBy('name', 'asc')->get()->getResultArray();
    }

    /** @return list<string> Tag names currently on this recipient, alphabetical. */
    public function namesForRecipient(int $recipientId): array
    {
        return array_column(
            db_connect()->table('recipient_tags rt')
                ->select('t.name')->join('tags t', 't.id = rt.tag_id')
                ->where('rt.recipient_id', $recipientId)->orderBy('t.name', 'asc')
                ->get()->getResultArray(),
            'name'
        );
    }

    /**
     * Replaces a recipient's tags with exactly the given set: parses a
     * comma-separated string (the form field's raw value), creates any tag
     * that doesn't exist yet, and drops any tag no longer in the list.
     */
    public function syncForRecipient(int $recipientId, string $commaSeparatedNames): void
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $n) => trim($n),
            explode(',', $commaSeparatedNames)
        ), static fn (string $n) => $n !== '')));

        $db = db_connect();
        $db->table('recipient_tags')->where('recipient_id', $recipientId)->delete();

        if ($names === []) {
            return;
        }

        $tagIds = array_map(fn (string $name) => $this->findOrCreate($name), $names);

        $db->table('recipient_tags')->insertBatch(array_map(
            static fn (int $tagId) => ['recipient_id' => $recipientId, 'tag_id' => $tagId],
            $tagIds
        ));
    }

    private function findOrCreate(string $name): int
    {
        $db = db_connect();
        $existing = $db->table('tags')->where('name', $name)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }

        // Two requests racing to create the same new tag name would otherwise
        // both pass the check above and collide on the unique key -- treat
        // that as "someone else just created it" and look it up instead of
        // erroring the whole save.
        try {
            $db->table('tags')->insert(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
            return (int) $db->insertID();
        } catch (\Exception $e) {
            $existing = $db->table('tags')->where('name', $name)->get()->getRowArray();
            if ($existing) {
                return (int) $existing['id'];
            }
            throw $e;
        }
    }
}
