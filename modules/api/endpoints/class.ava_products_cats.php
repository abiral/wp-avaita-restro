<?php

class Ava_Products_Cats {
    public function register_endpoints() {}
    
    public static function get_instance() {
        return new Ava_Products_Cats();
    }
}