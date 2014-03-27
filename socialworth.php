<?php

/**
 * Determines the social worth of a URL.
 * 
 * @see https://github.com/dsposito/socialworth
 */
class SocialWorth
{
    /**
     * The available network sources.
     *
     * @var array
     */
    protected $network_config = array(
        'facebook',
        'googleplus',
        'hackernews',
        'linkedin',
        'mozscape',
        'pinterest',
        'reddit',
        'stumbleupon',
        'twitter',
    );

    /**
     * The chosen network sources.
     *
     * @var array
     */
    protected $network_targets;

    /**
     * The URL to value.
     *
     * @var string
     */
    protected $url;

    /**
     * The open request connection.
     *
     * @var Resource|null
     */
    protected $connection;

    /**
     * Handles the object initialization.
     *
     * @param array $networks The desired network sources.
     * 
     * @return void
     */
    public function __construct(array $networks = array())
    {
        $this->network_targets = !empty($networks) ? $networks : $this->network_config;
    }

    /**
     * Handles the object destruction.
     */
    public function __destruct()
    {
        $this->closeConnection();
    }

    /**
     * Values the social worth of a given URL.
     *
     * @param string $url The URL to value.
     *
     * @return SocialWorth
     */
    public function value($url)
    {
        $this->url = $url;

        $worth = array();

        foreach($this->networks() as $name) {
            $request = $this->network($name);

            $params = array();
            if (isset($request['headers'])) $params['headers'] = $request['headers'];
            if (isset($request['payload'])) $params['payload'] = $request['payload'];

            $worth[$request['name']] = $request['callback'](
                $this->request($request['method'], $request['url'], $params)
            );
        }

        $worth['total'] = array_sum($worth);

        return $worth;
    }

    /**
     * Gets the networks from which to request social worth.
     *
     * @return array
     */
    protected function networks()
    {
        return $this->network_targets;
    }

    /**
     * Gets the details request details for a network.
     *
     * @param string $name Network name.
     *
     * @return array
     */
    protected function network($name)
    {
        $url = $this->url;

        if ($name == 'mozscape') {
            define('SEOMOZ_ID', 'YOUR ID HERE');
            define('SEOMOZ_KEY', 'YOUR KEY HERE');

            $urlseo = parse_url($url);
            if(!isset($urlseo['path'])) {
                $url .= '/';
                $urlseo = parse_url($url);
            }

            $expires    = time() + 300;
            $urlseo     = "{$urlseo['host']}{$urlseo['path']}";
            $seomoz_sig = (SEOMOZ_ID && SEOMOZ_KEY ? urlencode(base64_encode(hash_hmac('sha1', SEOMOZ_ID . "\n" . $expires, SEOMOZ_KEY, true))) : '');
        }

        $networks = array(
            'mozscape' => array(
                'name'     => 'mozscape',
                'method'   => 'GET',
                'url'      => "http://lsapi.seomoz.com/linkscape/url-metrics/{$urlseo}?Cols=32&AccessID=" . SEOMOZ_ID . "&Expires={$expires}&Signature={$seomoz_sig}",
                'callback' => function($resp) {
                    if (isset($resp->ueid)) {
                        return (int)$resp->ueid;
                    } else {
                        return 0;
                    }
                }
            ),
            'facebook' => array(
                'name'     => 'facebook',
                'method'   => 'GET',
                'url'      => 'https://graph.facebook.com/fql?q=' . urlencode("SELECT like_count, total_count, share_count, click_count, comment_count FROM link_stat WHERE url = \"{$url}\""),
                'callback' => function($resp) {
                    if (isset($resp->data[0]->total_count)) {
                        return (int)$resp->data[0]->total_count;
                    } else {
                        return 0;
                    }
                }
            ),
            'pinterest' => array(
                'name'     => 'pinterest',
                'method'   => 'GET',
                'url'      => "http://api.pinterest.com/v1/urls/count.json?url={$url}",
                'callback' => function($resp) {
                    $start  = strpos($resp, '{');
                    $length = strrpos($resp, '}') - $start + 1;
                    $json   = substr($resp, $start, $length);

                    $resp = json_decode($json);
                    if (isset($resp->count)) {
                        return (int)$resp->count;
                    } else {
                        return 0;
                    }
                }
            ),
            'twitter' => array(
                'name'     => 'twitter',
                'method'   => 'GET',
                'url'      => "http://cdn.api.twitter.com/1/urls/count.json?url={$url}",
                'callback' => function($resp) {
                    if ($resp && isset($resp->count)) {
                        return (int)$resp->count;
                    } else {
                        return 0;
                    }
                }
            ),
            'linkedin' => array(
                'name'     => 'linkedin',
                'method'   => 'GET',
                'url'      => "http://www.linkedin.com/countserv/count/share?url={$url}&format=json",
                'callback' => function($resp) {
                    if ($resp && isset($resp->count)) {
                        return (int)$resp->count;
                    } else {
                        return 0;
                    }
                }
            ),
            'stumbleupon' => array(
                'name'     => 'stumbleupon',
                'method'   => 'GET',
                'url'      => "http://www.stumbleupon.com/services/1.01/badge.getinfo?url={$url}",
                'callback' => function($resp) {
                    if ($resp && isset($resp->result) && isset($resp->result->views)) {
                        return (int)$resp->result->views;
                    } else {
                        return 0;
                    }
                }
            ),
            'reddit' => array(
                'name'     => 'reddit',
                'method'   => 'GET',
                'url'      => "http://www.reddit.com/api/info.json?url={$url}",
                'callback' => function($resp) {
                    if ($resp && isset($resp->data->children)) {
                        $c = 0;
                        foreach ($resp->data->children as $story) {
                            if (isset($story->data) && isset($story->data->ups)) {
                                $c = $c + (int)$story->data->ups;
                            }
                        }
                        return $c;
                    } else {
                        return 0;
                    }
                }
            ),
            'hackernews' => array(
                'name'     => 'hackernews',
                'method'   => 'GET',
                'url'      => "http://api.thriftdb.com/api.hnsearch.com/items/_search?q=&filter[fields][url]={$url}",
                'callback' => function($resp) {
                    if ($resp && isset($resp->results)) {
                        $c = 0;
                        foreach($resp->results as $story) {
                            $c++;
                            if (isset($story->item) && isset($story->item->points)) {
                                $c = $c + (int)$story->item->points;
                            }
                            if (isset($story->item) && isset($story->item->num_comments)) {
                                $c = $c + (int)$story->item->num_comments;
                            }
                        }
                        return $c;
                    } else {
                        return 0;
                    }
                }
            ),
            'googleplus' => array(
                'name'     => 'googleplus',
                'method'   => 'POST',
                'headers'  => array('Content-type: application/json'),
                'url'      => 'https://clients6.google.com/rpc',
                'payload'  => json_encode(array(
                    'method' => 'pos.plusones.get',
                    'id'     => 'p',
                    'params' => array(
                        'nolog'   => true,
                        'id'      => $url,
                        'source'  => 'widget',
                        'userId'  => '@viewer',
                        'groupId' => '@self'
                    ),
                    'jsonrpc'    => '2.0',
                    'key'        => 'p',
                    'apiVersion' => 'v1'
                    )),
                'callback' => function($resp) {
                    if (isset($resp->result->metadata->globalCounts->count)) {
                        return (int)$resp->result->metadata->globalCounts->count;
                    } else {
                        return 0;
                    }
                }
            ),
        );

        if (!array_key_exists($name, $networks)) {
            return false;
        }

        return $networks[$name];
    }

    /**
     * Gets the request connection.
     *
     * @return Resource
     */
    protected function getConnection()
    {
        if (!$this->connection) {
            $this->connection = curl_init();
        }

        return $this->connection;
    }

    /**
     * Closes the request connection.
     *
     * @return void
     */
    protected function closeConnection()
    {
        curl_close($this->connection);
    }

    /**
     * Performs a request.
     *
     * @param string $method The request type (GET, POST, PUT, DELETE).
     * @param string $url    The URL endpoint to request.
     * @param array  $params Optional request parameters.
     *
     * @return array
     */
    protected function request($method, $url, $params = array())
    {
        $connection = $this->getConnection();

        if (!$connection) {
            return false;
        }

        curl_setopt($connection, CURLOPT_POST, false);
        curl_setopt($connection, CURLOPT_POSTFIELDS, NULL);

        if (isset($params['headers'])) curl_setopt($connection, CURLOPT_HTTPHEADER, $params['headers']);

        if ($method == 'GET') {
            curl_setopt($connection, CURLOPT_HTTPGET, true);

        } elseif ($method == 'POST') {
            curl_setopt($connection, CURLOPT_POST, true);
            if(isset($params['payload'])) curl_setopt($connection, CURLOPT_POSTFIELDS, $params['payload']);

        } elseif ($method == 'PUT') {
            curl_setopt($connection, CURLOPT_CUSTOMREQUEST, 'PUT');

        } elseif ($method == 'DELETE') {
            curl_setopt($connection, CURLOPT_CUSTOMREQUEST, 'DELETE');

        }

        if ($method != 'POST' && count($params)) {
            foreach ($params as $p => $v) {
                $url .= $p . '=' . urlencode($v) . '&';
            }
        }

        curl_setopt($connection, CURLOPT_URL, rtrim($url, '?&'));
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);
        curl_setopt($connection, CURLOPT_HEADER, false);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($connection, CURLOPT_FOLLOWLOCATION, true);

        if ($raw = curl_exec($connection)) {
            if ($resp = json_decode($raw)) {
                return $resp;
            } else {
                return $raw;
            }
        }
    }
}