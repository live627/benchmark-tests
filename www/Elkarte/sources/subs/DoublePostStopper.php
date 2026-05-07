<?php

/**
 * @package   Double Post Stopper
 * @version   1.0
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2017, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */
class DoublePostStopper
{
    /**
     * The database object
     * @var database
     */
    protected $db = null;

    protected $groups = [];
    protected $units = ['m' => 60, 'h' => 3600, 'd' => 86400];
    protected $thresholds = ['threshold' => 0, 'unit' => 'm'];

    public function __construct()
    {
        $this->db = database();
    }

    /**
     * Register hooks to the system
     *
     * @return array
     */
    public static function registerAll()
    {
        $hook_functions = [
            ['integrate_display_topic', self::class.'::checkTopic'],
            ['integrate_action_post_before', self::class.'::canPost'],
            ['integrate_general_mod_settings', self::class.'::settings'],
            ['integrate_save_general_mod_settings', self::class.'::saveSettings'],
        ];
        foreach ($hook_functions as list($hook, $function)) {
            add_integration_function($hook, $function, '', false);
        }
    }

    public static function checkTopic()
    {
        global $options;

        if ((new self)->check()) {
            $options['display_quick_reply'] = 0;
        } // Disable quick reply; no sense giving that to the user if they can't use it.
    }

    public function check()
    {
        global $topic, $modSettings, $user_info;

        $this->db = database();
        $request = $this->db->query(
            '',
            '
        SELECT
            t.id_member_started, m.id_member, m.poster_time, m.poster_name, m.poster_email
        FROM {db_prefix}topics AS t
            JOIN {db_prefix}messages AS m ON (t.id_last_msg = m.id_msg)
        WHERE t.id_topic = {int:topic}
        LIMIT 1',
            [
                'topic' => $topic,
            ]
        );
        $bumpAttempt = false;
        $row = $this->db->fetch_assoc($request);
        $this->db->free_result($request);

        if ($row['poster_time'] + $this->getThreshold() > time()) {
            $bumpAttempt = true;
        }

        // So, is the last message ours?
        return $bumpAttempt && (($user_info['id'] > 0 && $row['id_member'] == $user_info['id']) || (isset($_POST['guestname']) && $_POST['guestname'] == $row['poster_name']) || (isset($_POST['email']) && $_POST['email'] == $row['poster_email']));
    }

    public function getThreshold()
    {
        global $user_info;

        return min(
            array_intersect_key(
                array_map(
                    function ($value) {
                        return $value['threshold'] * $this->units[$value['unit']];
                    },
                    $this->getThresholds()
                ),
                array_flip($user_info['groups'])
            )
        );
    }

    public function getThresholds()
    {
        global $modSettings;

        if (!empty($modSettings['doublePostThresholds'])) {
            $thresholds = json_decode($modSettings['doublePostThresholds'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $thresholds;
            }
        }

        return $this->thresholds;
    }

    public static function canPost()
    {
        global $topic;

        // I'm lazy.
        if (!empty($topic) && (new self)->check() && !isset($_REQUEST['msg'])) {
            loadLanguage('DoublePostStopper');
            \Errors::instance()->fatal_lang_error('double_post_attempt', false); // You naughty, naughty person!
        }
    }

    public static function settings(&$config_vars)
    {
        global $dps, $txt;

        loadLanguage('ManageBoards+DoublePostStopper');
        $dps = new self;
        $dps->groups = array_merge(
            [-1 => $txt['parent_guests_only']],
            $dps->getGroups()
        );
        $dps->thresholds = array_fill_keys(array_keys($dps->groups), $dps->thresholds);
        $dps->thresholds = $dps->getThresholds();
        $config_vars = array_merge(
            $config_vars,
            [
                [
                    'callback',
                    'doublePostThresholds',
                ],
            ]
        );
    }

    /**
     * Returns the groups that the admin can see.
     *
     * @package Membergroups
     * @return array
     */
    function getGroups()
    {
        $request = $this->db->query(
            '',
            '
        SELECT mg.id_group, mg.group_name
        FROM {db_prefix}membergroups AS mg'
        );
        $groups = [];
        while ($row = $this->db->fetch_assoc($request)) {
            $groups[$row['id_group']] = $row['group_name'];
        }
        $this->db->free_result($request);

        return $groups;
    }

    public static function saveSettings()
    {
        if (isset($_POST['doublePostThresholds'])) {
            $settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);
            $settingsForm->setConfigVars(
                [[
                    'text',
                    'doublePostThresholds',
                ]]);
            $settingsForm->setConfigValues(['doublePostThresholds' => json_encode($_POST['doublePostThresholds'])]);
            $settingsForm->save();
        }
    }

    function doublePostThresholdsCallback()
    {
        global $dps, $txt;

        echo '
                    </dl>
            <table class="table_grid">
                <thead>
                    <tr class="table_head">
                        <th colspan="2">', $txt['doublePostThresholds'], '</th>
                    </tr>
                    <tr class="table_head">
                        <td colspan="2" class="smalltext">', $txt['doublePostThresholds_desc'], '</td>
                    </tr>
                </thead>';

        foreach ($dps->groups as $id => $name) {
            echo '
                <tr class="standard_row">
                    <td>', $name, '</td>
                    <td>
                        <input type="int" value="', $dps->thresholds[$id]['threshold'], '" name="doublePostThresholds[', $id, '][threshold]" size="2" maxlength="2" min="0" />
                        <select name="doublePostThresholds[', $id, '][unit]">
                            <option value="m" ', $dps->thresholds[$id]['unit'] == 'm' ? 'selected' : '', '>', $txt['minutes'], '</option>
                            <option value="h" ', $dps->thresholds[$id]['unit'] == 'h' ? 'selected' : '', '>', $txt['hours'], '</option>
                            <option value="d" ', $dps->thresholds[$id]['unit'] == 'd' ? 'selected' : '', '>', $txt['days_word'], '</option>
                        </select>
                    </td>
                </tr>';
        }

        echo '
            </table>
                    <dl class="settings">';
    }
}

function template_callback_doublePostThresholds()
{
    global $dps;

    $dps->doublePostThresholdsCallback();
}