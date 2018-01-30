<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_SGMail
{
    public $mail;
    public $config;
    private $_from;
    private $_to = array();
    private $_cc = array();
    private $_bcc = array();
    private $_content_text;
    private $_content_html;
    private $_subject;
    private $_reply_to;
    private $_template_id;
    private $_attachment = array();
    private $_substitution = array();
    private $_custom_arg = array();
    private $_header = array();
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
    }

    public function from($name, $email)
    {
        $this->_from = new \SendGrid\Email($name, $email);

        $this->has_from = TRUE;

        return $this;
    }

    public function to($name, $email)
    {
        $this->_to[] = new \SendGrid\Email($name, $email);

        $this->has_to = TRUE;

        return $this;
    }

    public function cc($name, $email)
    {
        $this->_cc[] = new \SendGrid\Email($name, $email);

        return $this;
    }

    public function bcc($name, $email)
    {
        $this->_bcc[] = new \SendGrid\Email($name, $email);

        return $this;
    }

    public function replyTo($name, $email)
    {
        $this->_reply_to = new SendGrid\Email($name, $email);

        return $this;
    }

    public function subject($value)
    {
        $this->_subject = $value;

        return $this;
    }

    public function content($html, $text = '')
    {
        if ( ! empty($text))
            $this->_content_text = new SendGrid\Content('text/plain', $html);

        $this->_content_html = new SendGrid\Content('text/html', $html);

        return $this;
    }

    public function template($id)
    {
        $this->_template_id = $id;

        return $this;
    }

    public function substitution($key, $value = '')
    {
        if (is_array($key))
        {
            foreach($key as $k => $v)
                $this->_substitution[$k] = $v;
        }
        else
        {
            $this->_substitution[$key] = $value;
        }

        return $this;
    }

    public function custom_arg($key, $value = '')
    {
        if (is_array($key))
        {
            foreach($key as $k => $v)
                $this->_custom_arg[$k] = $v;
        }
        else
        {
            $this->_custom_arg[$key] = $value;
        }

        return $this;
    }

    public function header($key, $value = '')
    {
        if (is_array($key))
        {
            foreach($key as $k => $v)
                $this->_header[$k] = $v;
        }
        else
        {
            $this->_header[$key] = $value;
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

        $this->_attachment[] = $attachment;

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

        if ( ! $this->_content_html)
            $this->content('&nbsp;');

        $to = array_shift($this->_to);

        $this->mail = new \SendGrid\Mail($this->_from, $this->_subject, $to, $this->_content_html);
        $this->mail->setSendAt(time());

        if ($this->_reply_to)
            $this->mail->setReplyTo($this->_reply_to);

        if ($this->_content_text)
            $this->mail->addContent($this->_content_text);

        if ($this->_template_id)
            $this->mail->setTemplateId($this->_template_id);

        foreach($this->_attachment as $attachment)
            $this->mail->addAttachment($attachment);

        $personalization = $this->mail->getPersonalizations();
        $personalization = $personalization[0];

        if ($personalization)
        {
            foreach($this->_to as $email)
                $personalization->addTo($email);

            foreach($this->_cc as $email)
                $personalization->addCc($email);

            foreach($this->_bcc as $email)
                $personalization->addBcc($email);

            foreach($this->_substitution as $k => $v)
                $personalization->addSubstitution($k, $v);

            foreach($this->_custom_arg as $k => $v)
                $personalization->addCustomArg($k, $v);

            foreach($this->_header as $k => $v)
                $personalization->addHeader($k, $v);
        }

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