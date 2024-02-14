<?php

namespace Flits\Limechat\API;
use Flits\Limechat\LimechatProvider;

class Upload extends LimechatProvider {

    public $METHOD = "POST";
    public $URL = 'create';

    function __construct($config) {
        parent::__construct($config);
    }

}
