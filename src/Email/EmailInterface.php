<?php
namespace MarinePortal\Email;
interface EmailInterface { public function send(string $to, string $subject, string $html, ?string $text=null, array $opts=[]): bool; }
