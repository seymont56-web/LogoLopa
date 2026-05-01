<?php
header('Content-Type: application/json; charset=utf-8');

// Список слов для игры.
// image — необязателен: если файла нет, просто не показываем картинку.
$items = [
  ['word' => 'КОТ',    'hint' => 'Домашнее животное',          'image' => 'images/cat.png'],
  ['word' => 'СОК',    'hint' => 'Напиток из фруктов',          'image' => 'images/juice.png'],
  ['word' => 'ЛИМОН',  'hint' => 'Жёлтый кислый фрукт',         'image' => 'images/lemon.png'],
  ['word' => 'СНЕГ',   'hint' => 'Белое и холодное',            'image' => 'images/snow.png'],
  ['word' => 'ПЛИТА',  'hint' => 'На ней готовят еду',          'image' => 'images/stove.png'],
  ['word' => 'РАКЕТА', 'hint' => 'Летит к звёздам',             'image' => 'images/rocket.png'],
  ['word' => 'ПИТОН',  'hint' => 'Змея (и язык программирования)','image' => 'images/python.png'],
];

$out = [];
foreach ($items as $it) {
  $word = mb_strtoupper($it['word'], 'UTF-8');
  // Безопасное разбиение кириллических букв
  $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
  $out[] = [
    'word'    => $word,
    'letters' => $letters,
    'hint'    => $it['hint'],
    'image'   => $it['image'],
  ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
