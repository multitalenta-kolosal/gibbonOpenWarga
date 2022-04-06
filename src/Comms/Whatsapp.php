<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Comms;

use Gibbon\Contracts\Comms\Whatsapp as WhatsappInterface;


/**
 * Factory class to create a fully configured SMS client based on the chosen gateway.
 * 
 * @version v17
 * @since   v17
 */
class Whatsapp implements WhatsappInterface
{
    protected $whatsappApiKey;

    protected $to;

    protected $from;

    protected $content;

    public function __construct(array $config)
    {
        $this->to = [];
        $this->whatsappApiKey = $config['whatsappApiKey'];
    }

    /**
     * Set the message recipient(s).
     *
     * @param string|array $to
     */
    public function to($to)
    {
        $this->to = array_merge($this->to, is_array($to) ? $to : [$to]);

        return $this;
    }

    /**
     * Set the message sender name.
     *
     * @param string $from
     */
    public function from(string $from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the message content.
     *
     * @param string $from
     */
    public function content(string $content)
    {
        $this->content = stripslashes(strip_tags($content));

        return $this;
    }

    /**
     * Send the message to one or more recipients.
     *
     * @param array $to The recipient array.
     *
     * @return array Array of successful recipients.
     */
    public function send(array $recipients = []) : array
    {
        $sent = [];
        $recipients += array_merge($this->to, $recipients);

        foreach ($recipients as $recipient) {
            
            $my_apikey = $this->whatsappApiKey;
            $destination = substr_replace($recipient, "62", 0, 1);
            $message = $this->content;

            $api_url = "http://panel.rapiwha.com/send_message.php";
            $api_url .= "?apikey=". urlencode ($my_apikey);
            $api_url .= "&number=". urlencode ($destination);
            $api_url .= "&text=". urlencode ($message);

            $result = json_decode(file_get_contents($api_url, false));
            
            // echo "<br>Result: ". $my_result_object->success;
            // echo "<br>Description: ". $my_result_object->description;
            // echo "<br>Code: ". $my_result_object->result_code;
            
            
            if ($result->success) {
                $sent[] = $recipient;
            }
        }

        return $sent;
    }
}
