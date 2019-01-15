<?php
/*
Plugin Name: SERanking Slack Notifications
Plugin URI: https://seranking.com
Description: Get a notification on your Slack daily, for your SERP Rank upgrades
Version: 1.0.1
Author: Basilis Kanonidis
Author URI: https://creativeg.gr
Requires at least: 3.9.1
Tested up to: 4.1
Text Domain:
 */

include_once plugin_dir_path(__FILE__) . '/framework/init.php';
include_once plugin_dir_path(__FILE__) . '/api/class_serp_api.php';

class Serp_SlackNotification
{
    public function __construct()
    {
        add_action('init', array($this, 'admin_options'));
        add_action('init', array($this, 'serp_slack_cron'));
        // add_action('init', array($this, 'post_to_slack'));
        add_action('serp_slack_notification_daily_event', array($this, 'serp_slack_notification_daily_schedule'));
    }

    public function post_to_slack()
    {
        $objSerp = new class_serp_api();
        $sites = $objSerp->getAllSites();
        $sites = json_decode($sites, true);

        //getting list of search engines
        $searchEngines = file_get_contents(plugin_dir_path(__FILE__) . 'api/searchengines.json');
        $searchEngines = json_decode($searchEngines, true);
        $engines_array = array();
        foreach ($searchEngines as $engines) {
            $engines_array[$engines['id']] = $engines['name'];
        }
        $siteId = $objSerp->getSiteId($sites);
        if ($siteId) {
            $siteStats = $objSerp->getSiteStats($siteId);
            $keywordList = $objSerp->getKeywordList($siteId);
            $siteStats = json_decode($siteStats, true);

            $count = 0;
            $count1 = 0;
            $arry = array();
            foreach ($siteStats as $stats) {
                foreach ($stats['keywords'] as $keyword) {

                    $total_positions = count($keyword['positions']);

                    if ($total_positions > 0 && $keyword['positions'][$total_positions - 1]['change'] < 0) {

                        if (array_key_exists($stats['id'], $engines_array)) {
                            $searchEngine = $engines_array[$stats['id']];
                        }
                        $position = $keyword['positions'][$total_positions - 1]['pos'];

                        $arry['left'][$searchEngine]['p_' . $position][$count]['seid'] = $stats['id'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['keyword_id'] = $keyword['id'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['position'] = $keyword['positions'][$total_positions - 1]['pos'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['change'] = $keyword['positions'][$total_positions - 1]['change'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['keyword_name'] = $keywordList[$keyword['id']];

                        $arry['left'][$searchEngine]['p_' . $position][$count]['previous_date'] = $keyword['positions'][$total_positions - 2]['date'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['date'] = $keyword['positions'][$total_positions - 1]['date'];
                        $arry['left'][$searchEngine]['p_' . $position][$count]['previous_position'] = $keyword['positions'][$total_positions - 2]['pos'];

                        $count++;
                    }
                    if ($total_positions > 0 && $keyword['positions'][$total_positions - 1]['change'] > 0) {
                        if (array_key_exists($stats['id'], $engines_array)) {
                            $searchEngine = $engines_array[$stats['id']];
                        }
                        $position = $keyword['positions'][$total_positions - 1]['pos'];

                        $arry['entered'][$searchEngine]['p_' . $position][$count]['seid'] = $stats['id'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['keyword_id'] = $keyword['id'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['position'] = $keyword['positions'][$total_positions - 1]['pos'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['change'] = $keyword['positions'][$total_positions - 1]['change'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['keyword_name'] = $keywordList[$keyword['id']];

                        $arry['entered'][$searchEngine]['p_' . $position][$count]['previous_date'] = $keyword['positions'][$total_positions - 2]['date'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['date'] = $keyword['positions'][$total_positions - 1]['date'];
                        $arry['entered'][$searchEngine]['p_' . $position][$count]['previous_position'] = $keyword['positions'][$total_positions - 2]['pos'];

                        $count1++;
                    }
                }
            }

            if ($arry) {
                $hook_url = wp_get_page_field_value('serp-slack-settings', 'slack_webhook_url');
                $slack_channel = wp_get_page_field_value('serp-slack-settings', 'slack_channel');
                $slack_username = wp_get_page_field_value('serp-slack-settings', 'slack_username');

                //text display
                $text = "*" . $_SERVER['HTTP_HOST'] . "* Keyword \n";
                $check_duplicate = array();
                foreach ($arry['left'] as $key => $arr) {
                    foreach ($arr as $key1 => $arr1) {

                        foreach ($arr1 as $keyword) {
                            if (!in_array($key . $keyword['keyword_name'], $check_duplicate)) {
                                $text .= "_" . $keyword['date'] . "_ *" . $keyword['keyword_name'] . "* left from  _" . $keyword['previous_position'] . '_ to _' . $keyword['position'] . "_ in *" . $key . "*\n";
                                $check_duplicate[] = $key . $keyword['keyword_name'];
                            }

                        }

                    }
                }
                $objSerp->postToSlack($hook_url, $text, $slack_channel, $slack_username);

                $text = '';
                $check_duplicate = array();
                foreach ($arry['entered'] as $key => $arr) {
                    foreach ($arr as $key1 => $arr1) {
                        foreach ($arr1 as $keyword) {
                            if (!in_array($key . $keyword['keyword_name'], $check_duplicate)) {
                                $text .= "_" . $keyword['date'] . "_ *" . $keyword['keyword_name'] . "* entered from  _" . $keyword['previous_position'] . '_ to _' . $keyword['position'] . "_ in *" . $key . "* \n";
                                $check_duplicate[] = $key . $keyword['keyword_name'];
                            }

                        }

                    }
                }
                $objSerp->postToSlack($hook_url, $text, $slack_channel, $slack_username);
            }

            // post analytics to slack
            $this->getAnalytics($siteId);

            // post backlinks to slack
            $this->getBacklinks($siteId);

            // post competitors to slack
            $this->getCompetitors($siteId);
        }

    }
    private function getBacklinks($siteId)
    {
        $serp_username = wp_get_page_field_value('serp-slack-settings', 'serp_username');
        $serp_password = wp_get_page_field_value('serp-slack-settings', 'serp_password');
        $hook_url = wp_get_page_field_value('serp-slack-settings', 'slack_webhook_url');
        $slack_channel = wp_get_page_field_value('serp-slack-settings', 'slack_channel');
        $slack_username = wp_get_page_field_value('serp-slack-settings', 'slack_username');

        $objSerp = new class_serp_api($serp_username, $serp_password);
        $backlinks = $objSerp->getBacklinks($siteId);
        $backlinks = json_decode($backlinks, true);
        $text = "";
        if (!isset($backlinks['message'])) {
            $text .= "*Backlinks for the site*\n";
            if ($backlinks) {
                foreach ($backlinks as $backlink) {
                    $text .= "*From:" . $backlink['from_url'] . "*\n";
                    $text .= "*Domain :" . $backlink['domain'] . "*\n";
                    $text .= "*Anchor :" . $backlink['anchor'] . "*\n";
                    $text .= "*FB likes :" . $backlink['fb_likes'] . "*\n";
                    $text .= "*Date Added :" . $backlink['date_added'] . "*\n";
                    $text .= "*Date Placement :" . $backlink['date_placement'] . "*\n";
                    $text .= "\n";
                }
            } else {
                $text .= " Not available";
            }
            $objSerp->postToSlack($hook_url, $text, $slack_channel, $slack_username);
        }
    }

    public function getCompetitors($siteId)
    {
        $serp_username = wp_get_page_field_value('serp-slack-settings', 'serp_username');
        $serp_password = wp_get_page_field_value('serp-slack-settings', 'serp_password');
        $hook_url = wp_get_page_field_value('serp-slack-settings', 'slack_webhook_url');
        $slack_channel = wp_get_page_field_value('serp-slack-settings', 'slack_channel');
        $slack_username = wp_get_page_field_value('serp-slack-settings', 'slack_username');

        $objSerp = new class_serp_api($serp_username, $serp_password);
        $competitors = $objSerp->getCompetitors($siteId);
        $competitors = json_decode($competitors, true);
        $text = "";
        if (!isset($competitors['message'])) {
            $text .= " *Competitors for site*\n";
            if ($competitors) {
                $count = 1;
                foreach ($competitors as $competitor) {
                    $text .= "*" . $count . ". " . $competitor['url'] . " *\n";
                    $count++;
                }
            } else {
                $text .= " Not available";
            }

        }

        $objSerp->postToSlack($hook_url, $text, $slack_channel, $slack_username);

    }

    public function getAnalytics($siteId)
    {
        $serp_username = wp_get_page_field_value('serp-slack-settings', 'serp_username');
        $serp_password = wp_get_page_field_value('serp-slack-settings', 'serp_password');
        $hook_url = wp_get_page_field_value('serp-slack-settings', 'slack_webhook_url');
        $slack_channel = wp_get_page_field_value('serp-slack-settings', 'slack_channel');
        $slack_username = wp_get_page_field_value('serp-slack-settings', 'slack_username');

        $objSerp = new class_serp_api($serp_username, $serp_password);
        $analytics = $objSerp->getAnalytics($siteId, 'google');
        $analytics = json_decode($analytics, true);
        $text = "";
        if (!isset($analytics['message'])) {

            $text .= "*Analytics from google*\n";
            $text .= "*Query:" . $analytics['query'] . "*\n";
            $text .= "*Impressions :" . $analytics['impressions'] . "*\n";
            $text .= "*Clicks :" . $analytics['clicks'] . "*\n";
            $text .= "*Ctr :" . $analytics['ctr'] . "*\n";
            $text .= "*Avg :" . $analytics['avg'] . "*\n";
            $text .= "\n";
        }
        $analytics = $objSerp->getAnalytics($siteId, 'yandex');
        $analytics = json_decode($analytics, true);
        if (!isset($analytics['message'])) {
            $analytics = json_decode($analytics, true);
            $text .= "*Analytics from Yandex*\n";
            $text .= "*Query:" . $analytics['query'] . "*\n";
            $text .= "*Impressions :" . $analytics['impressions'] . "*\n";
            $text .= "*Clicks :" . $analytics['clicks'] . "*\n";
            $text .= "*Ctr :" . $analytics['ctr'] . "*\n";
            $text .= "*Avg :" . $analytics['avg'] . "*\n";
            $text .= "\n";
        }
        $objSerp->postToSlack($hook_url, $text, $slack_channel, $slack_username);

    }

    public function serp_slack_cron()
    {
        if (!wp_next_scheduled('serp_slack_notification_daily_event')) {
            wp_schedule_event(time(), 'daily', 'serp_slack_notification_daily_event');
        }
    }

    public function serp_slack_notification_daily_schedule()
    {
        $this->post_to_slack();
    }

    public function admin_options()
    {
        $page_with_tabs = wp_create_admin_page([
            'menu_name' => 'Serp Slack Settings',
            'id' => 'serp-slack-settings',
            'prefix' => 'sss_',
            'icon' => 'dashicons-share-alt',
        ]);
        $page_with_tabs->set_tab([
            'id' => 'default',
            'name' => 'Settings',

        ]);

        // creates a text field
        $page_with_tabs->add_field([
            'type' => 'text',
            'id' => 'serp_api_key',
            'label' => 'Serp API key',
            'desc' => 'Login to seranking and get api key from <a href="https://online.seranking.com/admin.user.settings.api.html
" target="_blank">https://online.seranking.com/admin.user.settings.api.html</a>',
        ]);

        // creates a text field
        $page_with_tabs->add_field([
            'type' => 'text',
            'id' => 'slack_webhook_url',
            'label' => 'Slack Webhook url',
            'desc' => 'You must first <a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">set up an incoming webhook integration in your Slack account</a>. <br>Once you select a channel (which you can override below),<br> click the button to add the integration, copy the provided webhook URL, and paste the URL in the box above.',
            'props' => [
                // optional tag properties
                'placeholder' => '',
            ],
            //'default' => 'hello world',
        ]);
        $page_with_tabs->add_field([
            'type' => 'text',
            'id' => 'slack_channel',
            'label' => 'Slack channel',
            'desc' => 'Incoming webhooks have a default channel but you can use this setting as an override. Use a "#" before the name to specify a channel and a "@" to specify a direct message. <br>For example, type "#wordpress" for your Slack channel about WordPress or type "@bamadesigner" to send your notifications to me as a direct message,<br> at least you could if I was a member of your Slack account. Send to multiple channels or messages by separating the names with commas.',
            'props' => [
                // optional tag properties
                'placeholder' => '',
            ],
            'default' => '',
        ]);

        $page_with_tabs->add_field([

            'type' => 'text',
            'id' => 'slack_username',
            'label' => 'Slack Username',
            'desc' => 'Incoming webhooks have a default username but you can use this setting as an override',
            'props' => [
                // optional tag properties
                'placeholder' => '',
            ],
        ]);

    }

    public static function render($view, $data = null)
    {
        // Handle data
        ($data) ? extract($data) : null;

        ob_start();
        include plugin_dir_path(__FILE__) . 'inc/' . $view . '.php';
        $view = ob_get_contents();
        ob_end_clean();

        return $view;
    }
}
new Serp_SlackNotification;
