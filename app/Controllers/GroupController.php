<?php

namespace App\Controllers;

use App\Services\ActivityLogger;
use App\Services\GroupService;
use CodeIgniter\Controller;

class GroupController extends Controller
{
    public function index()
    {
        return view('groups/index', [
            'title'  => 'Recipient Groups',
            'groups' => (new GroupService())->allWithCounts(),
        ]);
    }

    public function create()
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            session()->setFlashdata('error', 'Enter a group name.');
            return redirect()->to('/groups');
        }

        (new GroupService())->findOrCreate($name);
        ActivityLogger::log(session()->get('user_id'), 'group.created', 'Group created: ' . $name);
        session()->setFlashdata('success', 'Group "' . $name . '" created.');
        return redirect()->to('/groups');
    }

    public function delete($id)
    {
        (new GroupService())->delete((int) $id);
        ActivityLogger::log(session()->get('user_id'), 'group.deleted', 'Group #' . (int) $id . ' deleted');
        session()->setFlashdata('success', 'Group deleted.');
        return redirect()->to('/groups');
    }

    public function view($id)
    {
        $db = db_connect();
        $group = $db->table('groups')->where('id', $id)->get()->getRowArray();
        if (! $group) {
            return redirect()->to('/groups')->with('error', 'Group not found.');
        }

        $members = $db->table('recipient_groups rg')
            ->select('r.id, r.name, r.email, r.status')
            ->join('recipients r', 'r.id = rg.recipient_id')
            ->where('rg.group_id', $id)
            ->orderBy('r.name', 'asc')
            ->get()->getResultArray();

        $memberIds = array_column($members, 'id');
        $availableBuilder = $db->table('recipients')->select('id, name, email')->orderBy('name', 'asc');
        if ($memberIds !== []) {
            $availableBuilder->whereNotIn('id', $memberIds);
        }

        return view('groups/view', [
            'title'               => $group['name'],
            'group'               => $group,
            'members'             => $members,
            'availableRecipients' => $availableBuilder->get()->getResultArray(),
        ]);
    }

    /**
     * Adds recipients to a group, creating the group first if the name
     * doesn't exist yet -- shared by the Recipients bulk action, the CSV
     * import wizard, and the group detail page's own "add recipients"
     * control, so each of those only ever needs to post a name + ids.
     */
    public function addRecipients()
    {
        $name = trim((string) $this->request->getPost('group_name'));
        $ids  = array_filter(array_map('intval', $this->request->getPost('ids') ?? []));

        if ($name === '' || $ids === []) {
            session()->setFlashdata('error', 'Choose or name a group and select at least one recipient.');
            return redirect()->back();
        }

        $groupService = new GroupService();
        $groupId = $groupService->findOrCreate($name);
        $groupService->addRecipients($groupId, $ids);

        ActivityLogger::log(session()->get('user_id'), 'group.recipients_added', count($ids) . ' recipient(s) added to group: ' . $name);
        session()->setFlashdata('success', count($ids) . ' recipient(s) added to "' . $name . '".');
        return redirect()->back();
    }

    public function removeRecipient($groupId, $recipientId)
    {
        (new GroupService())->removeRecipient((int) $groupId, (int) $recipientId);
        ActivityLogger::log(session()->get('user_id'), 'group.recipient_removed', 'Recipient removed from group #' . (int) $groupId);
        session()->setFlashdata('success', 'Recipient removed from group.');
        return redirect()->to('/groups/view/' . (int) $groupId);
    }
}
