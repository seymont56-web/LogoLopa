<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'logop_games';
const DB_USER = 'root';
const DB_PASS = '';

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
