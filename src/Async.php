<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 2015/8/22
 * Time: 17:52
 */

namespace Jenner\Http;


class Async
{
    protected $curl;

    protected $works;

    protected $callback;

    const METHOD_POST = "post";

    const METHOD_GET = "GET";

    public function __construct($callback = null)
    {
        $this->curl = curl_multi_init();
        if ($this->curl === false) {
            throw new \RuntimeException("curl resource init failed");
        }

        if(!is_null($callback)){
            $this->callback = $callback;
        }else{
            $this->callback = array($this, "defaultCallback");
        }
    }

    public function attach(Task $task)
    {
        curl_multi_add_handle($this->curl, $task->getTask());
    }

    public function isDone()
    {
        return curl_multi_exec($this->curl, $active) != CURLM_CALL_MULTI_PERFORM;
    }

    public function execute()
    {
        $responses = array();
        $callback = $this->callback;
        do {
            while (($code = curl_multi_exec($this->curl, $active)) == CURLM_CALL_MULTI_PERFORM) ;

            if ($code != CURLM_OK) {
                break;
            }

            // a request was just completed -- find out which one
            while ($done = curl_multi_info_read($this->curl)) {

                // get the info and content returned on the request
                $info = curl_getinfo($done['handle']);
                $error = curl_error($done['handle']);
                $content = curl_multi_getcontent($done['handle']);

                if (is_null($callback) || !is_callable($callback)) {
                    $result = call_user_func(array($this, 'defaultCallback'), $content, $info, $error);
                } else {
                    $result = call_user_func($callback, $content, $info, $error);
                }

                $responses[] = compact('info', 'error', 'result');

                // remove the curl handle that just completed
                curl_multi_remove_handle($this->curl, $done['handle']);
                curl_close($done['handle']);
            }

            // Block for data in / output; error handling is done by curl_multi_exec
            if ($active > 0) {
                curl_multi_select($this->curl, 0.5);
            }

        } while ($active);

        curl_multi_close($this->curl);

        return $responses;
    }

    public function defaultCallback($content, $info, $error)
    {
        return $content;
    }
}