<?php
/**
 * CRM对接插件路由/鉴权配置。
 *
 * receive / products 两个接口供外部CRM调用, 不依赖禅道会话, 通过 X-Resource-Token 头或 ?token= 鉴权,
 * 因此注册到 openMethods(免登录) 与 logonMethods(允许匿名访问)。
 */
$config->openMethods[]  = 'crmsync.receive';
$config->openMethods[]  = 'crmsync.products';
$config->logonMethods[] = 'crmsync.receive';
$config->logonMethods[] = 'crmsync.products';
