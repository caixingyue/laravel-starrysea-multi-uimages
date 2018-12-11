<?php

namespace Starrysea\Uimages\Tests;

use Starrysea\Uimages\Images;

class ImagesGatherTest extends Images
{
    // open privacy mode
    protected $secret = true;

    // allow all domain cross-domain
    protected function crossDomainWhitelist()
    {
        return '*'; // all domain can access
    }

    // set secret picture access address
    protected function secretUrl()
    {
        return 'https://xingyue.test/img';
        // https:/xingyue.test/img/aW1hZ2VzL3
    }

    // set picture storage directory
    protected function storage()
    {
        return 'user/avatar';
    }

    // allow upload picture format
    protected function accept()
    {
        return ['png']; // only upload png picture
    }

    // success callback
    protected function call_success($filed, string $url, string $path)
    {
        dump('success', $url, $path);
    }

    // error callback
    protected function call_error(string $message, $data = '')
    {
        dump('error', $message, $data);
    }
}