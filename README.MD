# Github的OAuth2登陆插件

本插件的演示地址：https://newsn.net/say/github-oauth-flow.html 

安装完成后，请设置相关参数。
并在你希望显示对应按钮的地方，添加如下语句即可。
```php
<?php $this->user->SNAuthGithubIcon(); ?>
```

本插件授权登陆后，用户身份权限是最低的visitor身份。你懂的。

代码的编写过程中，参考了typecho的“新浪OAuth2登陆插件”的部分逻辑，原作者是jimmy chaw，http://x3d.cnblogs.com 。