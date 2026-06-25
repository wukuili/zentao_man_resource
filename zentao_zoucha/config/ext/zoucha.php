<?php
/**
 * 走查模块路由/权限相关全局配置。
 * 部署后位于禅道根目录 config/ext/zoucha.php，由 config/config.php 的 glob('ext/*.php') 全局加载。
 *
 * browse 设为 logonMethod：任何登录用户都可浏览走查列表，保证顶级菜单「项目走查」对所有登录用户可见。
 * export/diagnostic 不放行——export 已在 group 权限包中登记需授权；diagnostic 在控制器内限管理员。
 */
$config->logonMethods[] = 'zoucha.browse';
