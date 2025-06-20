<?php
class zimbra_password_redirect extends rcube_plugin
{
    private $rc;

    public function init()
    {
        $this->rc = rcmail::get_instance();
        $this->add_hook('login_after', array($this, 'check_password_change'));
        $this->add_hook('login_failed', array($this, 'check_password_change'));
    }

    public function check_password_change($args)
    {
        $imap = $this->rc->get_storage();
        $error_msg = $imap->get_error_str();

        // Hata mesajýnda "password must be changed" ifadesini ara
        if (strpos(strtolower($error_msg), 'password must be changed') !== false) {
            $redirect_url = $this->rc->config->get('zimbra_password_redirect_url', 'https://siteniz.com/sifremi-unuttum');
            header('Location: ' . $redirect_url);
            exit;
        }

        return $args;
    }
}
?>