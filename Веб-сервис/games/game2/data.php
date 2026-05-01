<?php
header('Content-Type: application/json; charset=utf-8');

$items = [
  [
    'word' => 'КОТ',
    'hint' => 'Мяукает и любит молоко',
    'image' => 'img/cat.png',
    'sound' => 'sounds/cat.mp3'
  ],
  [
    'word' => 'СОБАКА',
    'hint' => 'Лает и охраняет дом',
    'image' => 'img/dog.png',
    'sound' => 'sounds/dog.mp3'
  ],
  [
    'word' => 'КОРОВА',
    'hint' => 'Мычит и даёт молоко',
    'image' => 'img/cow.png',
    'sound' => 'sounds/cow.mp3'
  ],
  [
    'word' => 'УТКА',
    'hint' => 'Крякает и плавает в пруду',
    'image' => 'img/duck.png',
    'sound' => 'sounds/duck.mp3'
  ],
  [
    'word' => 'ЛЯГУШКА',
    'hint' => 'Квакает и прыгает у воды',
    'image' => 'img/frog.png',
    'sound' => 'sounds/frog.mp3'
  ],
  [
    'word' => 'ЛОШАДЬ',
    'hint' => 'Ржёт и быстро скачет',
    'image' => 'img/horse.png',
    'sound' => 'sounds/horse.mp3'
  ]
];

$out = [];

foreach ($items as $it) {
  $word = mb_strtoupper($it['word'], 'UTF-8');
  $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);

  $out[] = [
    'word' => $word,
    'letters' => $letters,
    'hint' => $it['hint'],
    'image' => $it['image'],
    'sound' => $it['sound']
  ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);