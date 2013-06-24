<?php
$request = $modx->getOption('request', $scriptProperties, null);
if (empty($request))
    return '';

$customrequestCorePath = $modx->getOption('customrequest.core_path',null,$modx->getOption('core_path').'components/customrequest/');
$customrequest = $modx->getService('customrequest','CustomRequest',$customrequestCorePath.'model/customrequest/',$scriptProperties);
$customrequest->initialize();
return $customrequest->makeUrl($request, $scriptProperties);