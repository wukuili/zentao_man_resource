<?php
if(!isset($config->zhoubao)) $config->zhoubao = new stdclass();

/* 企业微信群机器人 webhook（部署时在 config/ext 覆盖为真实地址） */
$config->zhoubao->wecomWebhook = '';
/* cronPush 防未授权调用的 token（部署时覆盖） */
$config->zhoubao->pushToken = 'CHANGE_ME';
/* 推送提示时间，仅文档展示，实际由 cron 决定 */
$config->zhoubao->pushDay  = 5;
$config->zhoubao->pushTime = '17:00';
