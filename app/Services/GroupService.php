<?php

namespace App\Services;

class GroupService
{
    /** @return list<array{id:int,name:string}> All group names, alphabetical -- used for datalist suggestions. */
    public function all(): array
    {
        return db_connect()->table('groups')->orderBy('name', 'asc')->get()->getResultArray();
    }

    /**
     * All groups with their total member count and how many of those are
     * "sendable" (active) -- used by both the Groups page and Compose's
     * recipient-group picker.
     *
     * @return list<array{id:int,name:string,total:int,sendable:int}>
     */
    public function allWithCounts(): array
    {
        $rows = db_connect()->table('groups g')
            ->select("g.id, g.name, COUNT(rg.recipient_id) AS total, SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) AS sendable")
            ->join('recipient_groups rg', 'rg.group_id = g.id', 'left')
            ->join('recipients r', 'r.id = rg.recipient_id', 'left')
            ->groupBy('g.id, g.name')
            ->orderBy('g.name', 'asc')
            ->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['id']       = (int) $row['id'];
            $row['total']    = (int) $row['total'];
            $row['sendable'] = (int) $row['sendable'];
        }

        return $rows;
    }

    /** @return list<string> Group names this recipient belongs to, alphabetical. */
    public function namesForRecipient(int $recipientId): array
    {
        return array_column(
            db_connect()->table('recipient_groups rg')
                ->select('g.name')->join('groups g', 'g.id = rg.group_id')
                ->where('rg.recipient_id', $recipientId)->orderBy('g.name', 'asc')
                ->get()->getResultArray(),
            'name'
        );
    }

    /** @return list<int> Active recipient ids in this group -- exactly the set Compose should be able to select. */
    public function sendableRecipientIds(int $groupId): array
    {
        return array_map('intval', array_column(
            db_connect()->table('recipient_groups rg')
                ->select('rg.recipient_id')->join('recipients r', 'r.id = rg.recipient_id')
                ->where('rg.group_id', $groupId)->where('r.status', 'active')
                ->get()->getResultArray(),
            'recipient_id'
        ));
    }

    /**
     * Creates a group by name if it doesn't already exist, returning its id
     * either way -- callers (the Groups page, the Recipients bulk action,
     * CSV import) all want "get me this group, creating it if needed" and
     * shouldn't have to check first.
     */
    public function findOrCreate(string $name): int
    {
        $name = trim($name);
        $db = db_connect();
        $existing = $db->table('groups')->where('name', $name)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }

        // Two requests racing to create the same new group name would
        // otherwise both pass the check above and collide on the unique key
        // -- treat that as "someone else just created it" and look it up
        // instead of erroring the whole save (same race TagService::findOrCreate() guards against).
        try {
            $db->table('groups')->insert(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
            return (int) $db->insertID();
        } catch (\Exception $e) {
            $existing = $db->table('groups')->where('name', $name)->get()->getRowArray();
            if ($existing) {
                return (int) $existing['id'];
            }
            throw $e;
        }
    }

    /**
     * Adds recipients to a group without disturbing their membership in any
     * other group -- unlike TagService::syncForRecipient(), this never
     * removes existing rows, since a recipient can belong to many groups at
     * once and "add to group" should never be destructive to the others.
     *
     * @param list<int> $recipientIds
     */
    public function addRecipients(int $groupId, array $recipientIds): void
    {
        $recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));
        if ($recipientIds === []) {
            return;
        }

        $existing = array_column(
            db_connect()->table('recipient_groups')
                ->select('recipient_id')->where('group_id', $groupId)->whereIn('recipient_id', $recipientIds)
                ->get()->getResultArray(),
            'recipient_id'
        );
        $toInsert = array_diff($recipientIds, array_map('intval', $existing));
        if ($toInsert === []) {
            return;
        }

        db_connect()->table('recipient_groups')->insertBatch(array_map(
            static fn (int $recipientId) => ['recipient_id' => $recipientId, 'group_id' => $groupId],
            array_values($toInsert)
        ));
    }

    public function removeRecipient(int $groupId, int $recipientId): void
    {
        db_connect()->table('recipient_groups')
            ->where('group_id', $groupId)->where('recipient_id', $recipientId)
            ->delete();
    }

    public function delete(int $groupId): void
    {
        // recipient_groups rows cascade via the FK; the group row itself
        // still needs its own delete.
        db_connect()->table('groups')->where('id', $groupId)->delete();
    }
}
