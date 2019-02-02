<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_SGMail
{
    public $mail;
    public $config;
    private $sg;
    private $has_from = FALSE;
    private $sender_nickname;

    public static function instance()
    {
		return new self();
    }

    public function __construct()
    {
        $this->config = Kohana::$config->load('sgmail');

        $this->sg = new \SendGrid($this->config->api_key);
        $this->mail = new \SendGrid\Mail\Mail();

        $this->sender_nickname = $this->config->sender_nickname;
	}

    public function from($email, $name = NULL)
    {
        $this->mail->setFrom($email, $name);

        $this->has_from = TRUE;

        return $this;
    }

    public function to($email, $name = NULL)
    {
        if (is_array($email))
            $this->mail->addTos($email);
        else
            $this->mail->addTo($email, $name);

        return $this;
    }

    public function cc($email, $name = NULL)
    {
        if (is_array($email))
            $this->mail->addCcs($email);
        else
            $this->mail->addCc($email, $name);

        return $this;
    }

    public function bcc($email, $name = NULL)
    {
        if (is_array($email))
            $this->mail->addBccs($email);
        else
            $this->mail->addBcc($email, $name);

        return $this;
    }

    public function replyTo($email, $name = NULL)
    {
        $this->mail->setReplyTo($email);

        return $this;
    }

    public function subject($value)
    {
        $this->mail->setSubject($value);

        return $this;
    }

    public function content($html, $text = '')
    {
        if (is_array($html))
        {
            $this->mail->addContents($html);
        }
        else
        {
            if ( ! empty($text))
                $this->mail->addContent('text/plain', $text);

            $this->mail->addContent('text/html', $html);
        }

        return $this;
    }

    public function template($id)
    {
        $this->mail->setTemplateId($id);

        return $this;
    }

    public function substitution($key, $value = '')
    {
        if (is_array($key))
            $this->mail->addDynamicTemplateDatas($key);
        else
            $this->mail->addDynamicTemplateData($key, $value);

        return $this;
    }

    public function attachment($file, $filename = NULL, $disposition = 'attachment', $content_id = NULL)
    {
        $content = file_get_contents($file);
        $file_type = File::mime_by_ext(pathinfo($file, PATHINFO_EXTENSION));

        if (empty($filename))
            $filename = strtolower(pathinfo($file, PATHINFO_BASENAME));

        if (empty($content_id))
            $content_id = md5($filename);

        $this->mail->addAttachment(
            base64_encode($content),
            $file_type,
            $filename,
            $disposition,
            $content_id
        );

        return $this;
    }

    public function header($key, $value = NULL)
    {
        if (is_array($key))
            $this->mail->addHeaders($key);
        else
            $this->mail->addHeader($key, $value);

        return $this;
    }

    public function customArg($key, $value = NULL)
    {
        if (is_array($key))
            $this->mail->addCustomArgs($key);
        else
            $this->mail->addCustomArg($key, $value);

        return $this;
    }

    public function sendAt($time)
    {
        if (is_string($time))
            $time = strtotime($time);

        $this->mail->setSendAt($time);

        return $this;
    }

    public function sender($nickname)
    {
        $this->sender_nickname = $nickname;

        return $this;
    }

    public function send()
    {
        $sender = $this->get_sender($this->sender_nickname);

        if ($sender AND ! $this->has_from)
            $this->from($sender->from->email, $sender->from->name);

        $response = $this->sg->send($this->mail);

        $ret = new stdClass();

        if ($response->statusCode() < 300)
            $ret->status = TRUE;
        else
            $ret->status = FALSE;

        $ret->code = $response->statusCode();
        $ret->body = $response->body();
        $ret->headers = $response->headers();

        return $ret;
    }

    public function get_sender($value = NULL)
    {
        if (is_numeric($value))
            $response = $this->sg->client->senders()->_($value)->get();
        else
            $response = $this->sg->client->senders()->get();

        if ($response->statusCode() < 300)
        {
            $data = json_decode($response->body());

            if (is_array($data))
            {
                if (empty($value))
                {
                    $data = current($data);
                }
                else
                {
                    $found = FALSE;

                    foreach ($data as $item)
                    {
                        if ($item->nickname == $value)
                        {
                            $found = TRUE;
                            $data = $item;

                            break;
                        }
                    }

                    if ( ! $found)
                        $data = NULL;
                }
            }

            return $data;
        }
        else
        {
            return NULL;
        }
    }
}