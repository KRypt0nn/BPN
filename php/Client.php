<?php

namespace BPN;

class Client
{
    public $node;
    public $trackers = array ();
    public $supportSockets = false;

    public function __construct ($port = 53236, $supportSockets = false)
    {
        $this->node = new Node ($port);
        $this->supportSockets = $supportSockets;
    }

    public function connect ($ip, $port = 53236)
    {
        $this->trackers[$ip .':'. $port] = new Pool ($ip, $port, $this->node->port, $this->supportSockets);

        return $this;
    }

    public function update ()
    {
        foreach ($this->trackers as $tracker)
            $tracker->update ();

        return $this;
    }

    /**
     * @param string $ip  - IP получателя
     * @param int $port   - port получателя
     * @param mixed $data - информация для отправки
     */
    public function send ($ip, $data, $port = 53236, $forceRetranslate = true)
    {
        if (!$forceRetranslate)
        {
            $support = true;

            foreach ($this->trackers as $tracker)
                if (!$tracker->user ($ip, $port)->supportSockets)
                {
                    $support = false;

                    break;
                }

            if ($support)
            {
                @file_get_contents ('http://'. $ip .':'. $port .'/'. Tracker::encode ($data));

                return $this;
            }
        }

        $mask = sha1 (uniqid (rand (PHP_INT_MIN, PHP_INT_MAX), true));

        foreach ($this->trackers as $tracker)
            $tracker->push ($ip, $port, $data, $mask);

        return $this;
    }

    public function recieve ()
    {
        $pop = array ();

        foreach ($this->trackers as $tracker)
            foreach ($tracker->pop () as $data)
                $pop[$data['mask']] = $data;

        return $pop;
    }

    public function listen ($callback = null, $cycle = false)
    {
        $this->node->listen (function (string $request, Socket $client) use ($callback)
        {
            $request = Tracker::decode (substr ($request, 5, strpos ($request, ' HTTP/') - 5));

            $callback ($request, $client);
        }, $cycle);

        return $this;
    }
}
