<?php
/**
 * 周报模块路由/权限相关全局配置。
 * 部署后位于禅道根目录 config/ext/zhoubao.php，由 config/config.php 的 glob('ext/*.php') 全局加载。
 *
 * browse 设为 logonMethod：任何登录用户都可浏览周报看板，保证顶级菜单「项目周报」对所有登录用户可见。
 * cronPush 同时设为 openMethod + logonMethod：供 cron/系统 crontab 匿名 curl 调用（无需登录），
 * 方法体内部仍会校验 token（$config->zhoubao->pushToken），token 是这条路径唯一的鉴权手段。
 * edit/view/export/copyLast/manage 不放行——已在 group 权限包中登记，须经授权，
 * 框架 commonModel::checkPriv() 会在每个请求自动拦截未授权方法。
 *
 * 部署时可在此覆盖企微 webhook 与推送 token（示例，取消注释并改为真实值）：
 * $config->zhoubao->wecomWebhook = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx';
 * $config->zhoubao->pushToken    = '换成一个随机字符串';
 */
$config->logonMethods[] = 'zhoubao.browse';
$config->openMethods[]  = 'zhoubao.cronPush';
$config->logonMethods[] = 'zhoubao.cronPush';
