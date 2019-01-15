<?php
class class_serp_api
{

    private $url = 'https://api4.seranking.com/';
    public $token = '';

    public function __construct()
    {

    }

    public function getAllSites()
    {
        $url = $this->url . 'sites';
        return $this->send($url);

    }

    public function getSearchEngines()
    {
        $method = 'searchEngines';
        $params = array('token' => $this->token);
        $params = http_build_query($params);
        $response = file_get_contents($this->url . '?method=' . $method . '&' . $params);
        return $response;
    }

    public function getSiteStats($siteId)
    {
        $url = $this->url . 'sites/' . $siteId . '/positions?';
        $dateStart = date('Y-m-d', strtotime(date('Y-m-d') . ' -1 day'));
        $dateEnd = date('Y-m-d');
        $params = array('dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'siteid' => $siteId, 'token' => $this->token);
        $params = http_build_query($params);
        $url .= $params;
        return $this->send($url);
    }

    public function getSiteId($sites)
    {
        $current_site = $_SERVER['HTTP_HOST'];
        if ($sites) {
            foreach ($sites as $site) {
                if (preg_match('/' . $current_site . '/i', $site['name']) || $current_site == $site['name'] || 'http://' . $current_site == $site['name'] || 'https://' . $current_site == $site['name']) {
                    return $site['id'];
                }
            }
        }
        return false;
    }

    public function getAnalytics($siteId, $site)
    {
        $url = $this->url . 'analytics/' . $siteId . '/' . $site;
        return $this->send($url);
    }

    public function getBacklinks($siteId)
    {
        $url = $this->url . 'backlinks/' . $siteId;
        $response = $this->send($url);
        return $response;
    }

    public function getCompetitors($siteId)
    {
        $url = $this->url . 'competitors/site/' . $siteId;
        $response = $this->send($url);
        return $response;
    }

    public function competitorKeywordPosition($competitorId)
    {
        $url = $this->url . 'competitors/' . $siteId . '/positions?';
        $dateStart = date('Y-m-d', strtotime(date('Y-m-d') . ' -1 day'));
        $dateEnd = date('Y-m-d');
        $params = array('dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'siteid' => $siteId, 'token' => $this->token);
        $params = http_build_query($params);
        $url .= $params;
        return $this->send($url);

    }

    public function postToSlack($hook_url, $message, $channel, $username = '')
    {
        $array = array("text" => $message, 'channel' => $channel, 'username' => $username);

        $data = wp_remote_post($hook_url, array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode($array),
            'method' => 'POST',
        ));
    }

    public function send($url, $method = 'GET')
    {
        $api_key = wp_get_page_field_value('serp-slack-settings', 'serp_api_key');
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'header' => [
                    "Authorization: Token $api_key",
                    "Content-Type: application/json; charset=utf-8",
                ],
            ],
        ]);

        $response = file_get_contents($url, 0, $context);
        if ($response) {
            return $response;
        } else {
            return false;
        }
    }

    public function getKeywordList($siteId)
    {
        $url = $this->url . 'sites/' . $siteId . '/keywords';
        $keywords = $this->send($url);
        if ($keywords) {
            $keywords = json_decode($keywords, true);
            $ret = array();
            foreach ($keywords as $keyword) {
                $ret[$keyword['id']] = $keyword['name'];
            }
            return $ret;
        } else {
            return false;
        }
    }

}
