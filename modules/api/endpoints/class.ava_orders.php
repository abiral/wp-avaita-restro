<?php

class Ava_Orders {
    public function register_endpoints() {}
    
    public static function get_instance() {
        return new Ava_Orders();
    }
}