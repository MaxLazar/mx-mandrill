<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/mandrill-api-php/src/Mandrill.php';

use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Header\HeaderConsts;


class Mx_mandrill_ext
{

    public $settings = [];
    public $config = [];

    public $version = '1.0.4';

    public function __construct($settings = '')
    {
        $this->config = ee()->config->item('mx_mandrill');

        if ($this->config) {
            $settings = $this->config;
        }

        $this->settings = $settings;
    }

    /**
     * [activate_extension description]
     * @return [type] [description]
     */
    public function activate_extension()
    {
        $this->settings = $this->getSettingsFromFile();

        $data = [
            [
                'class'    => __CLASS__,
                'method'   => 'email_send',
                'hook'     => 'email_send',
                'settings' => serialize($this->settings),
                'priority' => 10,
                'version'  => $this->version,
                'enabled'  => 'y'
            ]
        ];

        foreach ($data as $hook) {
            ee()->db->insert('extensions', $hook);
        }
    }

    /**
     * [disable_extension description]
     * @return [type] [description]
     */
    public function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
     * [update_extension description]
     * @param string $current [description]
     * @return [type]          [description]
     */
    public function update_extension($current = '')
    {
        // UPDATE HOOKS
        return true;
    }


    // --------------------------------
    //  Settings
    // --------------------------------

    public function settings()
    {
        $settings = array();

        $settings['mandrill_api_key'] = array('i', '', "");

        return $settings;
    }

    /**
     * Settings Form
     *
     * @param Array   Settings
     * @return  void
     */
    function settings_form($current)
    {
        $name = 'mx_mandrill';

        if ($current == '') {
            $current = array();
        } else {
            $current = $current;
        }

        if ($this->settings != '') {
            $current = $this->settings;
        }

        $defaults = array(
            'mandrill_api_key' => ''
        );

        $values = array_replace($defaults, $current);

        $vars = array(
            'base_url'              => ee('CP/URL')->make('addons/settings/' . $name . '/save'),
            'cp_page_title'         => 'MX Mandrill Settings',
            'save_btn_text'         => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving',
            'alerts_name'           => '',
            'sections'              => array(array())
        );

        $vars['sections'] = array(
            array(
                array(
                    'title'  => 'enable',
                    'fields' => array(
                        'enable' => array(
                            'type'     => 'toggle',
                            'value'    => $values['enable'],
                            'required' => false
                        )
                    )
                ),
                array(
                    'title'  => 'mandrill_api_key',
                    'fields' => array(
                        'mandrill_api_key' => array(
                            'type'     => 'text',
                            'value'    => $values['mandrill_api_key'],
                            'required' => true
                        )
                    )
                )
            )
        );

        return ee('View')->make('mx_mandrill:index')->render($vars);
    }

    /**
     * Save Settings
     *
     * This function provides a little extra processing and validation
     * than the generic settings form.
     *
     * @return void
     */
    function save_settings()
    {
        if (empty($_POST)) {
            show_error(lang('unauthorized_access'));
        }

        ee()->lang->loadfile('mx_mandrill');

        ee('CP/Alert')->makeInline('mx-sendgrid-save')
            ->asSuccess()
            ->withTitle(lang('message_success'))
            ->addToBody(lang('preferences_updated'))
            ->defer();

        ee()->db->where('class', __CLASS__);
        ee()->db->update('extensions', array('settings' => serialize($_POST)));


        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/mx_mandrill'));
    }


    /**
     * @param $data
     * @return bool|null
     */
    public function email_send($data)
    {
        $body = str_replace(array('{unwrap}', '{/unwrap}'), '', $data['finalbody']);

        if (!isset($this->settings['mandrill_api_key']) || $this->settings['enable'] != true) {
            return false;
        }

        $messageOrg = Message::from($data['header_str'] . $body);

        $data['text']      = $messageOrg->getTextContent();  // plain text body
        $data['html']      = $messageOrg->getHtmlContent(); // html body
        $data['from']      = $messageOrg->getHeader('From');
        $data['fromName']  = $data['from']->getName();
        $data['fromEmail'] = $data['from']->getEmail();

        $data['fromName'] = ($data['fromName'] == "From") ? '' : $data['fromName'];
        $data["subject"]  = isset($data["subject"]) ? $data["subject"] : '';


        if ($data['text'] === null && $data['html'] === null) {
            $data['text'] = $data['finalbody'];
        }

        $data['to_recipients']  = array();
        $data['cc_recipients']  = array();
        $data['bcc_recipients'] = array();

        // Set the recipient.
        if (!empty($data["recipients"])) {
            foreach ($data["recipients"] as $value) {
                $data['to_recipients'] = array_merge($data['to_recipients'], self::recipients2array($value));
            }
        }

        // Check Cc
        if (!empty($data['headers']['Cc'])) {
            $data['cc_recipients'] = self::recipients2array($data['headers']['Cc']);
        }

        // Check Bcc
        if (!empty($data['headers']['Bcc'])) {
            $data['bcc_recipients'] = self::recipients2array($data['headers']['Bcc']);
        }

        // Set the cc_array
        foreach ($data["cc_array"] as $key => $value) {
            $data['cc_recipients'] = array_merge($data['cc_recipients'], self::recipients2array($value));
        }

        // Set the bcc_array
        foreach ($data["bcc_array"] as $key => $value) {
            $data['bcc_recipients'] = array_merge($data['bcc_recipients'], self::recipients2array($value));
        }

        // Set headers
        $data["headers_x"] = [
            "User-Agent" => "ExpressionEngine",
            "X-Mailer"   => "ExpressionEngine (via MX)",
            "Reply-To"   => $data['headers']['Reply-To']
        ];

        $data['attachment'] = $messageOrg->getAllAttachmentParts();

        return self::send_api($data, $this->settings);
    }

    /**
     * @param $data
     * @param $host
     * @param $username
     * @param $password
     * @return bool
     */
    public function send_api($data, $settings)
    {
        $sent       = false;
        $tos        = [];
        $cc         = [];
        $bcc        = [];
        $parameters = [];

        $attachments = null;

        ee()->load->library('logger');

        $parameters['from_name']  = $data['fromName'];
        $parameters['from_email'] = $data['fromEmail'];
        $parameters['subject']    = $data['subject'];
        $parameters['text']       = $data['text'];
        $parameters['html']       = $data['html'];

        //Add the X-Header
        foreach ($data["headers_x"] as $name => $value) {
            $parameters['headers'][$name] = $value;
        }


        // Set the recipient.
        foreach ($data["to_recipients"] as $emailTo => $nameTo) {
            $parameters['to'][] = array(
                'email' => $emailTo,
                'name'  => $nameTo,
                'type'  => 'to'
            );
        }

        if (count($data['cc_recipients']) > 0) {
            foreach ($data["cc_recipients"] as $emailTo => $nameTo) {
                $parameters['to'][] = array(
                    'email' => $emailTo,
                    'name'  => $nameTo,
                    'type'  => 'cc'
                );
            }
        }

        if (count($data['bcc_recipients']) > 0) {
            foreach ($data["bcc_recipients"] as $emailTo => $nameTo) {
                $parameters['to'][] = array(
                    'email' => $emailTo,
                    'name'  => $nameTo,
                    'type'  => 'bcc'
                );
            }
        }
        /**/
        if ($data['attachment'] !== null) {
            foreach ($data['attachment'] as $index => $attachment) {
                // Build the file attachment.
                $parameters['attachments'][] = [
                    'content' => base64_encode($attachment->getContent()),
                    'name'    => $attachment->getFilename(),
                    'type'    => $attachment->getHeaderValue(HeaderConsts::CONTENT_TYPE)
                ];
            };
        }

        $mandrill = new Mandrill($this->settings['mandrill_api_key']);

        $async = false;

        try {
            $sent = true;

            $result = $mandrill->messages->send($parameters, $async);

            foreach ($result as $r) {
                if ($r['status'] == 'rejected') {
                    ee()->logger->developer("MX Mandrill: Message failed to create with: " . $r['reject_reason']);
                }
            }
        } catch (Mandrill_Error $e) {
            $sent = false;
            ee()->logger->developer("MX Mandrill: Message failed to create with: " . $e->getMessage());
        }


        /*
                print_r($data["to_recipients"]);
                print_r($data["cc_recipients"]);
                print_r($data['bcc_recipients']);
                die();
        */


        if ($sent == true) {
            ee()->extensions->end_script = true;
            return true;
        }

        return $sent;
    }


    protected function parseAddress(string $address, string $name): string
    {
        if (!empty($name)) {
            return sprintf('"%s" <%s>', $name, $address);
        }

        return $address;
    }


    /**
     * @param $str
     */
    public function recipients2array($str)
    {
        $results    = [];
        $recipients = explode(',', $str);

        foreach ($recipients as $email) {
            $results = array_merge($results, self::email_split(trim($email)));
        }

        return $results;
    }


    /**
     * [email_split description] Thanks to https://stackoverflow.com/questions/16685416/split-full-email-addresses-into-name-and-email
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    public function email_split($str)
    {
        if ($str == '') {
            return null;
        }

        $str .= " ";

        $re = '/(?:,\s*)?(.*?)\s*(?|<([^>]*)>|\[([^][]*)]|(\S+@\S+))/';
        preg_match_all($re, $str, $m, PREG_SET_ORDER, 0);

        $name  = (isset($m[0][1])) ? $m[0][1] : '';
        $email = (isset($m[0][2])) ? $m[0][2] : '';

        //return array('name' => trim($name), 'email' => trim($email));
        return array(trim($email) => trim($name));
    }


    /**
     * [initializeSettings description]
     * @return [type] [description]
     */
    private function initializeSettings()
    {
        // Set up app settings
        $settingData = [
        ];

        return serialize($settingData);
    }

    /**
     * [getSettingsFromFile description]
     * @return [type] [description]
     */
    private function getSettingsFromFile()
    {
        /*


        if (! file_exists(PATH_THIRD . 'mx_mandrill/config.php')) {
            return $this->initializeSettings();
        }

        require PATH_THIRD . 'mx_mandrill/config.php';



        return $settingData;
        */

        return array();
    }
}
