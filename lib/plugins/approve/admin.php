<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Extension\Event;

class admin_plugin_approve extends AdminPlugin
{
    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 1;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
        global $ID;
        /* @var Input */
        global $INPUT;

        /** @var helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');

        if($INPUT->str('action') && $INPUT->arr('assignment') && checkSecurityToken()) {
            $assignment = $INPUT->arr('assignment');
            //insert empty string as NULL
            if ($INPUT->str('action') === 'delete') {
                $db->deleteMaintainer((int) $assignment['id']);
                $db->updatePagesAssignments();
            } else if ($INPUT->str('action') === 'add' && !blank($assignment['assign'])) {
                $approver = '';
                if (!blank($assignment['approver'])) {
                    $approver = $assignment['approver'];
                } elseif (!blank($assignment['approver_fb'])) {
                    $approver = $assignment['approver_fb'];
                }
                $db->addMaintainer($assignment['assign'], $approver);
                $db->updatePagesAssignments();
            }

            send_redirect(wl($ID, array('do' => 'admin', 'page' => 'approve'), true, '&'));
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        global $ID;
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        echo $this->locale_xhtml('assignments_intro');

        echo '<form action="' . wl($ID) . '" action="post">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="approve" />';
        echo '<input type="hidden" name="sectok" value="' . getSecurityToken() . '" />';
        echo '<table class="inline">';

        // header
        echo '<tr>';
        echo '<th>'.$this->getLang('admin h_assignment_namespace').'</th>';
        echo '<th>'.$this->getLang('admin h_assignment_approver').'</th>';
        echo '<th></th>';
        echo '</tr>';

        /** @var helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');
        $assignments = $db->getMaintainers();

        foreach($assignments as $assignment) {
            $id = $assignment['id'];
            $namespace = $assignment['namespace'];
            $approver = $assignment['approver'] ?: '---';

            $link = wl(
                $ID, array(
                    'do' => 'admin',
                    'page' => 'approve',
                    'action' => 'delete',
                    'sectok' => getSecurityToken(),
                    'assignment[id]' => $id
                )
            );

            echo '<tr>';
            echo '<td>' . hsc($namespace) . '</td>';
            $user = $auth->getUserData($approver);
            if ($user) {
                echo '<td>' . hsc($user['name']) . '</td>';
            } else {
                echo '<td>' . hsc($approver) . '</td>';
            }
            echo '<td><a href="' . $link . '">'.$this->getLang('admin btn_delete').'</a></td>';
            echo '</tr>';
        }

        // new assignment form
        echo '<tr>';
        echo '<td><input type="text" name="assignment[assign]" /></td>';
        echo '<td>';
        if ($auth->canDo('getUsers')) {
            echo '<select name="assignment[approver]">';
            echo '<option value="">---</option>';
            if ($auth->canDo('getGroups')) {
                foreach($auth->retrieveGroups() as $group) {
                    echo '<option value="@' . hsc($group) . '">' . '@' . hsc($group) . '</option>';
                }
            }
            foreach($auth->retrieveUsers() as $login => $data) {
                echo '<option value="' . hsc($login) . '">' . hsc($data['name']) . '</option>';
            }
            echo '</select>';
            // in case your auth plugin can do groups, but not list them (like the default one),
            // leave a text field as backup
            if (!$auth->canDo('getGroups')) {
                echo '<input name="assignment[approver_fb]" id="plugin__approve_group_input">';
            }
        } else {
            echo '<input name="assignment[approver]">';
        }
        echo '</td>';

        echo '<td><button type="submit" name="action" value="add">'.$this->getLang('admin btn_add').'</button></td>';
        echo '</tr>';

        echo '</table>';
    }
}

// vim:ts=4:sw=4:et:
