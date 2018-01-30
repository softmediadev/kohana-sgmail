<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_SGMail
{
    public $mail;
    public $personalization;
    public $config;
    private $sg;
    private $has_from = FALSE;
    private $has_to = FALSE;

    public static function instance()
    {
		return new self();
    }

    public function __construct()
    {
        $this->config = Kohana::$config->load('sgmail');

        $this->sg = new \SendGrid($this->config->api_key);
        $this->mail = new \SendGrid\Mail();
        $this->personalization = new SendGrid\Personalization();
	}

    public function from($name, $email)
    {
        $email = new \SendGrid\Email($name, $email);
        $this->mail->setFrom($email);

        $this->has_from = TRUE;

        return $this;
    }

    public function to($name, $email)
    {
        $email = new SendGrid\Email($name, $email);
        $this->personalization->addTo($email);

        $this->has_to = TRUE;

        return $this;
    }

    public function cc($name, $email)
    {
        $email = new SendGrid\Email($name, $email);
        $this->personalization->addCc($email);

        return $this;
    }

    public function bcc($name, $email)
    {
        $email = new SendGrid\Email($name, $email);
        $this->personalization->addBcc($email);

        return $this;
    }

    public function replyTo($name, $email)
    {
        $email = new SendGrid\Email($name, $email);
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
        if ( ! empty($text))
        {
            $content = new SendGrid\Content('text/plain', $text);
            $this->mail->addContent($content);
        }

        $content = new SendGrid\Content('text/html', $html);
        $this->mail->addContent($content);

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
        {
            foreach($key as $k => $v)
                $this->personalization->addSubstitution($k, $v);
        }
        else
        {
            $this->personalization->addSubstitution($key, $value);
        }

        return $this;
    }

    public function attachment($file, $filename = '')
    {
        $content = file_get_contents($file);

        if (empty($filename))
            $filename = strtolower(pathinfo($file, PATHINFO_BASENAME));

        $attachment = new SendGrid\Attachment();
        $attachment->setContent(base64_encode($content));
        $attachment->setType(File::mime_by_ext(pathinfo($file, PATHINFO_EXTENSION)));
        $attachment->setDisposition('attachment');
        $attachment->setFilename($filename);
        $this->mail->addAttachment($attachment);

        return $this;
    }

    public function send()
    {
        if ( ! empty($this->config->sender_id) AND ! $this->has_from)
        {
            $sender = $this->get_sender($this->config->sender_id);

            $this->from($sender->from->name, $sender->from->email);
        }

        if ( ! empty($this->config->sender_to_id) AND ! $this->has_to)
        {
            $sender = $this->get_sender($this->config->sender_to_id);

            $this->to($sender->from->name, $sender->from->email);
        }

        $this->mail->addPersonalization($this->personalization);

        $response = $this->sg->client->mail()->send()->post($this->mail);

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

    public function get_sender($param = '')
    {
        if (empty($param))
            $response = $this->sg->client->senders()->get();
        else
            $response = $this->sg->client->senders()->_($param)->get();

        if ($response->statusCode() < 300)
            return json_decode($response->body());
        else
            return NULL;
    }
}