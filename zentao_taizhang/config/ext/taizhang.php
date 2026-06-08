<?php
/**
 * 台账模块路由/权限相关全局配置。
 * 本文件部署后位于禅道根目录 config/ext/taizhang.php，由 config/config.php 的 glob('ext/*.php') 全局加载。
 *
 * browse 设为 logonMethod：任何登录用户都可浏览台账列表（保证顶级菜单「项目台账」对所有登录用户可见）。
 * edit/delete/export 不放行——它们已在 group 权限包中登记，必须经授权才能访问，
 * 框架 commonModel::checkPriv() 会在每个请求自动拦截未授权方法。
 */
$config->logonMethods[] = 'taizhang.browse';
