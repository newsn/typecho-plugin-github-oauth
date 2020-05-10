<?php
/**
 * SNAuth Plugin
 *
 * @copyright  Copyright (c) 2017 newsn (https://newsn.net)
 * @license    GNU General Public License 2.0
 * 
 */
require_once 'AuthFunction.php';

class SNAuth_AuthAction extends Typecho_Widget implements Widget_Interface_Do {

    private $db;
    private $config;
    private static $pluginName = 'SNAuth';
    private static $tableName = 'users_oauth';
    private $AuthFunction = null;

    public function __construct($request, $response, $params = NULL) {
        parent::__construct($request, $response, $params);
        $this->config = Helper::options()->plugin(self::$pluginName);
        $this->db = Typecho_Db::get();
        $this->AuthFunction = new SNAuth_AuthFunction();
    }

    public function action() {
        $authorize_url = "https://github.com/login/oauth/authorize?client_id=" . $this->config->client_id . "&scope=user&state=" . md5(time());
        $this->response->redirect($authorize_url);
        exit;
    }

    private function ok() {
        $this->response->redirect("/oauth/github/ok");
    }

    public function okView() {
        echo "授权成功！<script>window.opener.location.reload();self.close();</script>";
    }

    public static function AuthIcon() {
        $html=<<<EOT
            <link rel="stylesheet" href="/usr/plugins/SNAuth/asset/oauth.css">
            <span class="github_login_icon">使用GitHub登陆</span>
            <script src="/usr/plugins/SNAuth/asset/oauth.js"></script>
EOT;
        return $html;
    }
    
    public function callback() {
        if (empty($_GET['code'])) {
            throw new Typecho_Exception(_t('无效请求！'));
        }
        $parameters = [
            "code" => trim($_GET['code']),
            "state" => trim($_GET['state']),
            'client_id' => $this->config->client_id,
            'client_secret' => $this->config->client_secret,
            'redirect_uri' => $this->config->callback_url
        ];
        $json_str = $this->AuthFunction->oAuthRequest("https://github.com/login/oauth/access_token", "POST", $parameters);
        $json = json_decode($json_str);
        $access_token = $json->access_token;
        if (empty($access_token)) {
            throw new Typecho_Exception(_t('获取access_token失败，请返回重新授权！'));
            exit();
        }
        //$user_str = $this->AuthFunction->oAuthRequest("https://api.github.com/user", "GET", ["access_token" => $access_token]);
        //200510，https://developer.github.com/changes/2020-02-10-deprecating-auth-through-query-param/
        $user_str = $this->AuthFunction->oAuthRequest("https://api.github.com/user", "GetWithHeader", ["Authorization:token ".$access_token]);
        $user_json = json_decode($user_str);
        $user = $this->AuthFunction->object2array($user_json);
        if (!key_exists("login", $user)) {
            throw new Typecho_Exception(_t('获取授权信息失败，请返回重新授权！'));
            exit();
        }
        $table = $this->db->getPrefix() . self::$tableName;
        $query = $this->db->query("SELECT * FROM {$table} WHERE openid='{$user["id"]}' AND platform='github'");
        $users_oauth = $this->db->fetchRow($query);
        if (!empty($users_oauth['uid'])) {
            //该帐号已经绑定了用户
            if (!Typecho_Widget::widget('Widget_User')->hasLogin()) {
                $this->setUserLogin($users_oauth['uid']);
            }
        } else {
            $platform = "github";
            $expire_time = 3600 * 24;
            //该帐号未绑定过
            if (Typecho_Widget::widget('Widget_User')->hasLogin()) {
                //已经登录,直接绑定
                $cookieUid = Typecho_Cookie::get('__typecho_uid');
                $this->bindOauthUser($cookieUid, $user["id"], $platform, $expire_time);
            } else {
                //取用户信息
                $uid = $this->registerFromGithubUser($user);
                if (!$uid) {
                    throw new Typecho_Exception(_t('创建帐号失败，请联系管理员！'));
                }
                $this->setUserLogin($uid);
                $this->bindOauthUser($uid, $user["id"], $platform, $expire_time);
            }
        }
        $this->ok();
        exit();
    }

    /**
     * 根据用户信息创建帐号
     */
    protected function registerFromGithubUser(&$user) {
        $hasher = new PasswordHash(8, true);
        $generatedPassword = Typecho_Common::randString(7);
        $uname = $user['login'];
        if (!$this->nameExists($uname)) {
            for ($i = 1; $i < 999; $i++) {
                if ($this->nameExists($uname . '_' . $i)) {
                    $uname = $uname . '_' . $i;
                    break;
                }
            }
        }
        $dataStruct = array(
            'name' => $uname,
            //'mail' => $user['id'] . '@github.com|' . $user['email'],
            //'mail' => $user['id'] . '@github.com',
            'mail' => $user['email'],
            'screenName' => $uname,
            'password' => $hasher->HashPassword($generatedPassword),
            'created' => time(),
            'url' => ($user['blog'] == "") ? $user["html_url"] : $user["blog"],
            'group' => 'visitor'
        );
        $insertId = Typecho_Widget::widget('Widget_Abstract_Users')->insert($dataStruct);
        return $insertId;
    }

    public function nameExists($name) {
        $select = $this->db->select()
                ->from('table.users')
                ->where('name = ?', $name)
                ->limit(1);
        $user = $this->db->fetchRow($select);
        return $user ? false : true;
    }

    /**
     * 设置用户登陆状态
     */
    protected function setUserLogin($uid, $expire = 30243600) {
        Typecho_Widget::widget('Widget_User')->simpleLogin($uid);
        $authCode = function_exists('openssl_random_pseudo_bytes') ?
                bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
        Typecho_Cookie::set('__typecho_uid', $uid, time() + $expire);
        Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), time() + $expire);
        //更新最后登录时间以及验证码
        $this->db->query($this->db
                        ->update('table.users')
                        ->expression('logged', 'activated')
                        ->rows(array('authCode' => $authCode))
                        ->where('uid = ?', $uid));
    }

    public function bindOauthUser($uid, $openid, $platform = 'github', $expires_in = 0) {
        $rows = array(
            'openid' => $openid,
            'uid' => $uid,
            'platform' => $platform,
            'bind_time' => time(),
            'expires_in' => $expires_in
        );
        return $this->db->query($this->db->insert('table.users_oauth')->rows($rows));
    }

}
