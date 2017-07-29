<?php

/**
 * 第三方登陆插件newsn.net
 * 
 * @package SNAuth
 * @author 苏南
 * @version 0.0.1
 * @link https://newsn.net
 */
class SNAuth_Plugin implements Typecho_Plugin_Interface {

    private static $pluginName = 'SNAuth';
    private static $tableName = 'users_oauth';

    public static function activate() {
        $meg = self::install();
        Typecho_Plugin::factory('Widget_User')->___SNAuthGithubIcon = array('SNAuth_AuthAction', 'AuthIcon');
        Helper::addAction('SNAuthAuthorize', 'SNAuth_AuthAction');
        Helper::addRoute('SNAuthAuthorize', '/oauth/github/', 'SNAuth_AuthAction', 'action');
        Helper::addRoute('SNAuthCallback', '/oauth/github/callback', 'SNAuth_AuthAction', 'callback');
        Helper::addRoute('SNAuthOK', '/oauth/github/ok', 'SNAuth_AuthAction', 'okView');
        return _t($meg . '。请进行<a href="options-plugin.php?config=' . self::$pluginName . '">初始化设置</a>');
    }

    public static function install() {
        $installDb = Typecho_Db::get();
        $prefix = $installDb->getPrefix();
        $oauthTable = $prefix . self::$tableName;
        try {
            $installDb->query("ALTER TABLE " . $prefix . "users DROP INDEX mail"); //email可以重复
            $installDb->query("CREATE TABLE `$oauthTable` (
                        `moid` int(10) unsigned NOT NULL AUTO_INCREMENT,
                      `platform` varchar(45) NOT NULL DEFAULT 'sina',
                      `uid` int(10) unsigned NOT NULL,
                      `openid` varchar(80) NOT NULL,
                      `bind_time` int(10) unsigned NOT NULL,
                      `expires_in` int(10) unsigned DEFAULT NULL,
                      `refresh_token` varchar(300) DEFAULT NULL,
                      PRIMARY KEY (`moid`),
                      KEY `uid` (`uid`),
                      KEY `platform` (`platform`),
                      KEY `openid` (`openid`)
                        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

            return('表创建成功, 插件已经被激活!');
        } catch (Typecho_Db_Exception $e) {
            //var_dump($e);
            $code = $e->getCode();
            if (('Mysql' == $type && 1050 == $code)) {
                $script = 'SELECT `moid` from `' . $oauthTable . '`';
                $installDb->query($script, Typecho_Db::READ);
                return '数据表已存在，插件启用成功';
            } else {
                //throw new Typecho_Plugin_Exception('数据表' . $oauthTable . '建立失败，插件启用失败。错误号：' . $code);
                return "ok?";
            }
        }
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
        $uninstallDb = Typecho_Db::get();
        $prefix = $uninstallDb->getPrefix();
        try {
            $uninstallDb->query("ALTER TABLE " . $prefix . "users ADD UNIQUE(mail)"); //email可以重复
            $uninstallDb->query("delete * from " . $prefix . "users_oauth where platform='github'");
        } catch (Typecho_Db_Exception $e) {
            
        }
        Helper::removeRoute('SNAuthOK');
        Helper::removeRoute('SNAuthAuthorize');
        Helper::removeRoute('SNAuthCallback');
        Helper::removeAction('SNAuthAuthorize');
    }

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $client_id = new Typecho_Widget_Helper_Form_Element_Text('client_id', NULL, '', _t('App Key'), '请在github平台查看https://github.com/settings/developers');
        $form->addInput($client_id);
        $client_secret = new Typecho_Widget_Helper_Form_Element_Text('client_secret', NULL, '', _t('App Secret'), '请在github平台查看https://github.com/settings/developers');
        $form->addInput($client_secret);
        $callback_url = new Typecho_Widget_Helper_Form_Element_Text('callback_url', NULL, 'http://', _t('回调地址'), '请与github平台中设置一致');
        $form->addInput($callback_url);
    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {
        
    }

}
