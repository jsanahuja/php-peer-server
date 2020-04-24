<?php

namespace Sowe\PHPPeerServer;

class Mapping implements \Countable, \IteratorAggregate{

    private $elements;

    public function __construct(){
        $this->elements = [];
    }

    public function add($element){
        $this->elements[$element->getId()] = $element;
    }

    public function remove($element){
        if (isset($this->elements[$element->getId()])) {
            unset($this->elements[$element->getId()]);
        }
    }

    public function contains($element){
        return isset($this->elements[$element->getId()]);
    }

    public function hasKey($key){
        return isset($this->elements[$key]);
    }

    public function get($id){
        if (isset($this->elements[$id])) {
            return $this->elements[$id];
        }
        return false;
    }

    public function keys(){
        return array_keys($this->elements);
    }

    public function values(){
        return array_values($this->elements);
    }
 
    // Countable
    public function count(){
        return count($this->elements);
    }

    // IteratorAggregate
    public function getIterator(){
        return new \ArrayIterator($this->elements);
    }    
}
