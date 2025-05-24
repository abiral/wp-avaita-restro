<?php

class Ava_Products {
    public function register_endpoints() {}
    
    public static function get_instance() {
        return new Ava_Products();
    }
}