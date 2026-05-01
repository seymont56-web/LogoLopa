<?php
require_once __DIR__ . '/inc/auth.php';

$isLogged = is_logged_in();
$role = $_SESSION['role'] ?? null;

$playHref = 'login.php';

if ($role === 'teacher') {
  $playHref = 'teacher/dashboard.php';
} elseif ($role === 'student') {
  $playHref = 'student/home.php';
}
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <title>Логопола — игры, которые учат говорить</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">

  <style>
    body {
      margin: 0;
      overflow-x: hidden;
      background: #eeefff;
    }

    html {
      scroll-behavior: smooth;
    }

    section[id] {
      scroll-margin-top: 110px;
    }

    .landing-page {
      min-height: 100vh;
      font-family: 'Montserrat', sans-serif;
      overflow: hidden;
    }

    .landing-page *,
    .landing-page *::before,
    .landing-page *::after {
      box-sizing: border-box;
    }

    .landing-page a {
      text-decoration: none;
    }

    .landing-container {
      width: min(calc(100% - 480px), 1440px);
      margin: 0 auto;
    }

    .landing-grid {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 20px;
    }

    /* ===== Header ===== */

    .login-header {
      position: fixed;
      top: clamp(16px, 1.5vw, 24px);
      left: 50%;
      z-index: 9999;

      width: min(calc(100% - 480px), 1440px);
      height: clamp(60px, 4.5vw, 72px);
      transform: translateX(-50%);

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      align-items: center;
      gap: 20px;

      padding: 0 clamp(24px, 2.5vw, 40px);
      border-radius: 999px;

      background: rgba(27, 21, 102, .5);
      backdrop-filter: blur(15px);
      -webkit-backdrop-filter: blur(15px);

      box-shadow:
        0 10px 28px rgba(0, 0, 0, .45),
        0 0 28px rgba(255, 255, 255, .12),
        inset 0 1px 10px rgba(255, 255, 255, .1),
        inset 0 -1px 10px rgba(255, 255, 255, .1);
    }

    .mobile-menu-btn {
      display: none;
    }

    .login-logo {
      grid-column: 1 / 2;
      justify-self: start;

      width: clamp(40px, 2.875vw, 46px);
      height: clamp(40px, 2.875vw, 46px);

      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-logo img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
    }

    .login-nav {
      grid-column: 4 / 10;

      display: flex;
      align-items: center;
      justify-content: center;
      gap: clamp(24px, 2.1vw, 46px);
    }

    .login-nav a {
      font-family: 'Montserrat', sans-serif;
      color: rgba(255, 255, 255, .62);
      text-decoration: none;
      font-size: clamp(13px, .95vw, 16px);
      white-space: nowrap;
      transition: color 0.3s ease;
    }

    .login-nav a:first-child,
    .login-nav a:hover {
      color: rgba(255, 255, 255, 1);
    }

    .login-header-btn {
      grid-column: 10 / 13;
      justify-self: end;

      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);

      min-width: clamp(120px, 9.375vw, 150px);
      height: clamp(36px, 2.5vw, 40px);
      border-radius: 999px;

      display: flex;
      align-items: center;
      justify-content: center;

      background: #ff9f0a;
      color: #fff;
      text-decoration: none;
      text-transform: uppercase;

      transition: box-shadow 0.2s ease;
    }

    .login-header-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .35);
    }

    /* ===== Hero ===== */

    .landing-hero {
      position: relative;
      z-index: 1;
      min-height: 1080px;
      overflow: hidden;
      isolation: isolate;
      background: #29209d;
    }

    .landing-hero::after {
      content: "";
      position: absolute;
      left: 45%;
      bottom: -235px;
      z-index: 3;

      width: 132vw;
      height: 380px;

      transform: translateX(-50%) rotate(-1.5deg);
      border-radius: 50% 50% 0 0 / 100% 100% 0 0;

      background: #eeefff;
      pointer-events: none;
    }

    .landing-bg {
      position: absolute;
      inset: 0 0 145px 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
    }

    .landing-bg img {
      position: absolute;
      display: block;
      user-select: none;
      pointer-events: none;
    }

    .landing-bg__cloud--1 {
      top: 118px;
      left: 90px;
      width: 285px;
      opacity: .55;
    }

    .landing-bg__cloud--2 {
      top: 145px;
      right: 235px;
      width: 405px;
      opacity: .78;
    }

    .landing-bg__cloud--3 {
      top: 108px;
      right: 85px;
      width: 260px;
      opacity: .5;
    }

    .landing-bg__line--1 {
      left: -980px;
      top: 490px;
      width: 80%;
      opacity: .25;
    }

    .landing-bg__line--2 {
      right: -600px;
      top: 80px;
      width: 60%;
      opacity: .24;
    }

    .landing-bg__star--1 {
      top: 132px;
      right: 710px;
      width: 25px;
      opacity: .5;
    }

    .landing-bg__star--2 {
      top: 318px;
      right: 230px;
      width: 46px;
      opacity: .5;
    }

    .landing-bg__star--3 {
      top: 300px;
      left: 745px;
      width: 42px;
      opacity: .5;
    }

    .landing-hero__grid {
      position: relative;
      z-index: 2;
      align-items: center;
      padding-top: 160px;
    }

    .landing-hero__content {
      grid-column: 1 / 6;
    }

    .landing-brand {
      margin-bottom: 42px;
      text-transform: uppercase;
    }

    .landing-brand__title {
      font-family: Borsok;
      color: transparent;
      -webkit-text-stroke: 1px rgba(255, 255, 255, .32);
      font-size: clamp(28px, 2vw, 31px);
      line-height: 1;
    }

    .landing-brand__subtitle {
      margin-top: 7px;
      color: rgba(255, 255, 255, .42);
      font-size: clamp(14px, .9vw, 16px);
      font-weight: 700;
      line-height: 1.4;
      letter-spacing: .02em;
    }

    .landing-hero h1 {
      margin: 0;
      color: #EBECFF;
      font-family: Borsok;
      font-size: clamp(46px, 3.8vw, 61px);
      line-height: 1.05;
      font-weight: 900;
      text-transform: uppercase;
    }

    .landing-hero p {
      width: 540px;
      max-width: 100%;
      margin: 34px 0 72px;

      color: rgba(235, 236, 255, 0.8);
      font-size: 20px;
      line-height: 1.2;
      font-weight: 400;
    }

    .landing-play {
      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);

      width: 360px;
      max-width: 100%;
      height: 64px;

      display: inline-flex;
      align-items: center;
      justify-content: center;

      border-radius: 999px;
      background: #ff9f0a;
      color: #fff;

      text-transform: uppercase;

      box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .landing-play:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .5);
      transform: scale(1.03);
    }

    .landing-hero__image {
      grid-column: 6 / 13;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      animation: lopaFloat 3s ease-in-out infinite;
    }

    @keyframes lopaFloat {
      0% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-18px);
      }

      100% {
        transform: translateY(0);
      }
    }

    .landing-hero__image img {
      width: 645px;
      max-width: 100%;
      display: block;
    }

    /* ===== Lopa frame ===== */

    .landing-lopa-section {
      position: relative;
      z-index: 4;
      height: 560px;
      padding: 0;
      background: #eeefff;
      overflow: visible;
    }

    .landing-lopa-frame {
      grid-column: 1 / 13;
      position: relative;
      z-index: 5;
      margin-top: -210px;
      transition: transform 0.2s ease;
    }

    .landing-lopa-frame:hover {
      transform: scale(1.01);
    }

    .landing-lopa-frame__img {
      width: 100%;
      height: auto;
      display: block;
    }

    .landing-lopa-list-grid {
      position: absolute;
      inset: 0;
      z-index: 6;
      pointer-events: none;
    }

    .landing-lopa-list {
      grid-column: 8 / 13;
      align-self: center;

      padding-top: 22px;
      padding-left: 124px;
      padding-right: 100px;

      display: flex;
      flex-direction: column;
      gap: 52px;

      pointer-events: auto;
    }

    .landing-lopa-list__item {
      display: grid;
      grid-template-columns: 74px minmax(0, 1fr);
      align-items: center;
      gap: 54px;
      transition: transform 0.2s ease;
    }

    .landing-lopa-list__item:hover {
      transform: scale(1.03);
    }

    .landing-lopa-list__item img {
      width: 100px;
      height: 100px;
      display: block;
    }

    .landing-lopa-list__item p {
      max-width: 100%;
      margin: 0;
      color: #ffffff;
      font-size: 16px;
      line-height: 1.08;
      font-weight: 600;
    }

    /* ===== Как это работает ===== */

    .landing-how-section {
      position: relative;
      z-index: 3;
      padding: 70px 0 160px;
      background: #eeefff;
      overflow: hidden;
    }

    .landing-how-bg {
      position: absolute;
      z-index: 0;
      pointer-events: none;
      user-select: none;
      opacity: .35;
    }

    .landing-how-bg--1 {
      left: -420px;
      top: 0px;
      width: 65%;
    }

    .landing-how-bg--2 {
      right: -170px;
      top: 80px;
      width: 65%;
    }

    .landing-how-title {
      grid-column: 1 / 13;
      position: relative;
      z-index: 2;

      margin: 0 0 55px;

      font-family: Borsok;
      font-size: clamp(34px, 3vw, 48px);
      line-height: 1;
      color: #171735;
      text-transform: uppercase;
    }

    .landing-how-cards {
      grid-column: 1 / 13;
      position: relative;
      z-index: 2;

      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 34px;

      align-items: stretch;
    }

    .landing-how-card {
      position: relative;

      min-height: 215px;
      height: 215px;

      padding: 34px 155px 30px 28px;

      background: transparent;
      box-shadow: none;
      overflow: visible;

      transition: transform .2s ease;
    }

    .landing-how-card:hover {
      transform: scale(1.03);
    }

    .landing-how-card__bg {
      position: absolute;
      inset: 0;
      z-index: 1;

      width: 100%;
      height: 100%;

      display: block;
      object-fit: fill;

      filter: drop-shadow(0 14px 18px rgba(22, 20, 70, .18));

      pointer-events: none;
      user-select: none;
    }

    .landing-how-card__content {
      position: relative;
      z-index: 2;

      width: 100%;
      padding: 0;
    }

    .landing-how-card h3 {
      margin: 0 0 22px;

      font-family: Borsok;
      font-size: clamp(24px, 1.35vw, 32px);
      line-height: 1;
      color: #ff9f0a;
      text-transform: uppercase;
    }

    .landing-how-card p {
      margin: 0;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(16px, .85vw, 20px);
      line-height: 1.12;
      font-weight: 700;
      color: #171735;
    }

    .landing-how-card__pic {
      position: absolute;
      z-index: 3;

      right: 18px;
      bottom: 18px;

      display: block;
      object-fit: contain;

      pointer-events: none;
      user-select: none;
    }

    .landing-how-card__pic--book {
      width: 145px;
    }

    .landing-how-card__pic--pc {
      width: 140px;
    }

    .landing-how-card__pic--gamepad {
      width: 150px;
    }

    /* ===== 1401–1919px ===== */

    @media (max-width: 1919px) and (min-width: 1401px) {
      section[id] {
        scroll-margin-top: 100px;
      }

      .landing-container,
      .login-header {
        width: calc(100% - 160px);
        max-width: 1440px;
      }

      .landing-hero {
        min-height: 920px;
      }

      .landing-hero__grid {
        padding-top: 145px;
      }

      .landing-hero h1 {
        font-size: 54px;
      }

      .landing-hero p {
        width: 500px;
        margin: 30px 0 56px;
        font-size: 18px;
        line-height: 1.22;
      }

      .landing-play {
        width: 330px;
        height: 60px;
      }

      .landing-hero__image img {
        width: 555px;
      }

      .landing-hero::after {
        bottom: -205px;
        height: 335px;
      }

      .landing-lopa-section {
        height: 485px;
      }

      .landing-lopa-frame {
        margin-top: -180px;
      }

      .landing-lopa-list {
        padding-left: 96px;
        padding-right: 66px;
        gap: 38px;
      }

      .landing-lopa-list__item {
        grid-template-columns: 68px minmax(0, 1fr);
        gap: 36px;
      }

      .landing-lopa-list__item img {
        width: 78px;
        height: 78px;
      }

      .landing-lopa-list__item p {
        font-size: 14px;
      }

      .landing-how-section {
        padding: 56px 0 135px;
      }

      .landing-how-title {
        margin-bottom: 44px;
        font-size: 42px;
      }

      .landing-how-cards {
        gap: 24px;
      }

      .landing-how-card {
        min-height: 192px;
        height: 192px;
        padding: 28px 112px 24px 24px;
      }

      .landing-how-card h3 {
        margin-bottom: 16px;
        font-size: 24px;
      }

      .landing-how-card p {
        font-size: 15px;
      }

      .landing-how-card__pic--book {
        width: 108px;
      }

      .landing-how-card__pic--pc {
        width: 104px;
      }

      .landing-how-card__pic--gamepad {
        width: 112px;
      }
    }

    /* ===== 1440px ===== */

    @media (max-width: 1440px) {
      section[id] {
        scroll-margin-top: 96px;
      }

      .landing-container {
        width: calc(100% - 96px);
        max-width: 1180px;
      }

      .landing-grid {
        gap: 16px;
      }

      .login-header {
        top: 18px;

        width: calc(100% - 96px);
        max-width: 1180px;
        height: 64px;

        gap: 16px;
        padding: 0 28px;
      }

      .login-logo {
        width: 42px;
        height: 42px;
      }

      .login-nav {
        grid-column: 3 / 10;
        gap: 26px;
      }

      .login-nav a {
        font-size: 14px;
      }

      .login-header-btn {
        grid-column: 10 / 13;

        min-width: 126px;
        height: 38px;
        font-size: 17px;
      }

      .landing-hero {
        min-height: 820px;
      }

      .landing-hero::after {
        bottom: -175px;
        height: 300px;
        width: 135vw;
      }

      .landing-bg {
        inset: 0 0 110px 0;
      }

      .landing-bg__cloud--1 {
        top: 105px;
        left: 58px;
        width: 220px;
      }

      .landing-bg__cloud--2 {
        top: 122px;
        right: 180px;
        width: 320px;
      }

      .landing-bg__cloud--3 {
        top: 96px;
        right: 54px;
        width: 210px;
      }

      .landing-bg__line--1 {
        left: -660px;
        top: 390px;
        width: 78%;
      }

      .landing-bg__line--2 {
        right: -430px;
        top: 70px;
        width: 58%;
      }

      .landing-hero__grid {
        padding-top: 132px;
      }

      .landing-brand {
        margin-bottom: 30px;
      }

      .landing-hero h1 {
        font-size: 48px;
      }

      .landing-hero p {
        width: 480px;
        margin: 28px 0 48px;
        font-size: 17px;
        line-height: 1.25;
      }

      .landing-play {
        width: 310px;
        height: 56px;
        font-size: 18px;
      }

      .landing-hero__image img {
        width: 500px;
      }

      .landing-lopa-section {
        height: 440px;
      }

      .landing-lopa-frame {
        margin-top: -158px;
      }

      .landing-lopa-list {
        padding-left: 84px;
        padding-right: 58px;
        gap: 32px;
      }

      .landing-lopa-list__item {
        grid-template-columns: 66px minmax(0, 1fr);
        gap: 34px;
      }

      .landing-lopa-list__item img {
        width: 76px;
        height: 76px;
      }

      .landing-lopa-list__item p {
        font-size: 14px;
      }

      .landing-how-section {
        padding: 48px 0 120px;
      }

      .landing-how-title {
        margin-bottom: 38px;
        font-size: 40px;
      }

      .landing-how-cards {
        gap: 22px;
      }

      .landing-how-card {
        min-height: 190px;
        height: 190px;
        padding: 28px 112px 24px 24px;
      }

      .landing-how-card h3 {
        margin-bottom: 16px;
        font-size: 24px;
      }

      .landing-how-card p {
        font-size: 15px;
      }

      .landing-how-card__pic--book {
        width: 112px;
      }

      .landing-how-card__pic--pc {
        width: 108px;
      }

      .landing-how-card__pic--gamepad {
        width: 116px;
      }
    }

    /* ===== 1280px ===== */

    @media (max-width: 1280px) {
      section[id] {
        scroll-margin-top: 90px;
      }

      .landing-container {
        width: calc(100% - 72px);
        max-width: 1100px;
      }

      .login-header {
        width: calc(100% - 72px);
        max-width: 1100px;
        height: 62px;

        padding: 0 24px;
      }

      .login-nav {
        grid-column: 3 / 10;
        gap: 20px;
      }

      .login-nav a {
        font-size: 13px;
      }

      .login-header-btn {
        min-width: 116px;
        height: 36px;
        font-size: 16px;
      }

      .landing-hero {
        min-height: 780px;
      }

      .landing-hero__grid {
        padding-top: 125px;
      }

      .landing-hero h1 {
        font-size: 44px;
      }

      .landing-hero p {
        width: 445px;
        font-size: 16px;
        line-height: 1.25;
        margin: 24px 0 42px;
      }

      .landing-play {
        width: 285px;
        height: 54px;
        font-size: 17px;
      }

      .landing-hero__image img {
        width: 455px;
      }

      .landing-lopa-section {
        height: 400px;
      }

      .landing-lopa-frame {
        margin-top: -140px;
      }

      .landing-lopa-list {
        padding-left: 72px;
        padding-right: 46px;
        gap: 26px;
      }

      .landing-lopa-list__item {
        grid-template-columns: 58px minmax(0, 1fr);
        gap: 28px;
      }

      .landing-lopa-list__item img {
        width: 66px;
        height: 66px;
      }

      .landing-lopa-list__item p {
        font-size: 13px;
      }

      .landing-how-section {
        padding: 42px 0 105px;
      }

      .landing-how-title {
        margin-bottom: 34px;
        font-size: 36px;
      }

      .landing-how-cards {
        gap: 18px;
      }

      .landing-how-card {
        min-height: 170px;
        height: 170px;
        padding: 22px 72px 20px 20px;
      }

      .landing-how-card h3 {
        margin-bottom: 12px;
        font-size: 20px;
      }

      .landing-how-card p {
        font-size: 12.5px;
        line-height: 1.08;
      }

      .landing-how-card__pic {
        right: 8px;
        bottom: 10px;
      }

      .landing-how-card__pic--book {
        width: 78px;
      }

      .landing-how-card__pic--pc {
        width: 76px;
      }

      .landing-how-card__pic--gamepad {
        width: 82px;
      }
    }

    /* ===== Мобильная версия landing ===== */

    @media (max-width: 768px) {
      section[id] {
        scroll-margin-top: 24px;
        scroll-margin-bottom: 96px;
      }

      body {
        background: #eeefff;
        padding-bottom: 86px;
      }

      .landing-page {
        overflow: hidden;
      }

      .landing-container {
        width: calc(100% - 32px);
        max-width: 100%;
      }

      .landing-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
      }

      /* ===== Header mobile bottom ===== */

      .login-header {
        position: fixed;
        top: auto;
        bottom: 14px;
        left: 50%;

        width: calc(100% - 24px);
        height: 58px;
        transform: translateX(-50%);

        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;

        padding: 0 14px;
        border-radius: 999px;
      }

      .login-logo {
        display: none;
      }

      .mobile-menu-btn {
        grid-column: 1 / 2;
        justify-self: start;

        width: 38px;
        height: 38px;
        padding: 0;

        border: 0;
        border-radius: 50%;

        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;

        background: rgba(255, 255, 255, .16);
        cursor: pointer;
      }

      .mobile-menu-btn span {
        width: 17px;
        height: 2px;

        display: block;

        border-radius: 999px;
        background: #fff;

        transition: transform .2s ease, opacity .2s ease;
      }

      .mobile-menu-btn.is-open span:nth-child(1) {
        transform: translateY(6px) rotate(45deg);
      }

      .mobile-menu-btn.is-open span:nth-child(2) {
        opacity: 0;
      }

      .mobile-menu-btn.is-open span:nth-child(3) {
        transform: translateY(-6px) rotate(-45deg);
      }

      .login-nav {
        position: fixed;
        left: 14px;
        right: 14px;
        bottom: 86px;
        z-index: 9998;

        grid-column: auto;

        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;

        padding: 12px;
        border-radius: 26px;

        background: rgba(27, 21, 102, .72);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);

        box-shadow:
          0 14px 32px rgba(0, 0, 0, .35),
          inset 0 1px 10px rgba(255, 255, 255, .1);

        opacity: 0;
        transform: translateY(12px) scale(.98);
        pointer-events: none;

        transition: opacity .2s ease, transform .2s ease;
      }

      .login-nav.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }

      .login-nav a {
        width: 100%;
        min-height: 44px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;

        color: rgba(255, 255, 255, .72);
        background: rgba(255, 255, 255, .08);

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
      }

      .login-nav a:first-child {
        color: #fff;
        background: rgba(255, 159, 10, .95);
      }

      .login-header-btn {
        grid-column: 2 / 3;
        justify-self: end;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      /* ===== Hero mobile ===== */

      .landing-hero {
        min-height: auto;
        padding: 18px 0 112px;
      }

      .landing-hero::after {
        left: 50%;
        bottom: -95px;
        z-index: 1;

        width: 160vw;
        height: 180px;

        transform: translateX(-50%) rotate(-2deg);
      }

      .landing-bg {
        inset: 0;
      }

      .landing-bg__cloud--1 {
        top: 72px;
        left: -42px;
        width: 150px;
        opacity: .35;
      }

      .landing-bg__cloud--2 {
        top: 126px;
        right: -72px;
        width: 210px;
        opacity: .42;
      }

      .landing-bg__cloud--3 {
        display: none;
      }

      .landing-bg__line--1 {
        left: -250px;
        top: 360px;
        width: 520px;
        opacity: .16;
      }

      .landing-bg__line--2 {
        right: -220px;
        top: 48px;
        width: 430px;
        opacity: .16;
      }

      .landing-bg__star--1 {
        top: 72px;
        right: 78px;
        width: 18px;
        opacity: .45;
      }

      .landing-bg__star--2 {
        top: 306px;
        right: 34px;
        width: 28px;
        opacity: .4;
      }

      .landing-bg__star--3 {
        top: 248px;
        left: 28px;
        width: 24px;
        opacity: .35;
      }

      .landing-hero__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));

        padding-top: 0;
        align-items: center;
      }

      .landing-hero__content {
        display: contents;
      }

      .landing-brand {
        grid-column: 1 / 3;
        order: 1;

        display: block;
        margin: 0 0 12px;

        text-align: center;
      }

      .landing-brand__title {
        font-size: 25px;
        -webkit-text-stroke: 1px rgba(255, 255, 255, .36);
      }

      .landing-brand__subtitle {
        margin-top: 6px;

        font-size: 12px;
        line-height: 1.2;
      }

      .landing-hero__image {
        grid-column: 1 / 3;
        order: 2;

        justify-content: center;

        margin: 0 0 12px;
      }

      .landing-hero__image img {
        width: min(76vw, 315px);
      }

      .landing-hero h1 {
        grid-column: 1 / 3;
        order: 3;

        justify-self: center;

        max-width: 390px;
        margin: 0;

        font-size: 37px;
        line-height: .98;
        text-align: center;
      }

      .landing-hero p {
        grid-column: 1 / 3;
        order: 4;

        justify-self: center;

        width: 100%;
        max-width: 380px;

        margin: 16px 0 24px;

        font-size: 15px;
        line-height: 1.24;
        text-align: center;
      }

      .landing-hero p br {
        display: none;
      }

      .landing-play {
        grid-column: 1 / 3;
        order: 5;

        justify-self: center;

        width: 100%;
        max-width: 290px;
        height: 50px;

        font-size: 16px;
      }

      /* ===== Lopa mobile ===== */

      .landing-lopa-section {
        position: relative;
        z-index: 10;

        height: auto;
        padding: 0 0 58px;
        margin-top: -42px;

        overflow: visible;
        background: #eeefff;
      }

      .landing-lopa-frame {
        grid-column: 1 / 3;

        position: relative;
        z-index: 12;

        margin-top: 0;

        background: #29209d;
        border-radius: 32px;
        overflow: hidden;

        box-shadow: 0 18px 32px rgba(22, 20, 70, .18);
      }

      .landing-lopa-frame:hover {
        transform: none;
      }

      .landing-lopa-frame__img {
        display: none;
      }

      .landing-lopa-list-grid {
        position: relative;
        inset: auto;
        z-index: 13;

        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));

        padding: 28px 20px;

        pointer-events: auto;
      }

      .landing-lopa-list {
        grid-column: 1 / 3;

        padding: 0;
        gap: 16px;
      }

      .landing-lopa-list__item {
        grid-template-columns: 54px minmax(0, 1fr);
        gap: 14px;

        min-height: 82px;
        padding: 14px;
        border-radius: 22px;

        background: rgba(255, 255, 255, .1);
      }

      .landing-lopa-list__item:hover {
        transform: none;
      }

      .landing-lopa-list__item img {
        width: 54px;
        height: 54px;
      }

      .landing-lopa-list__item p {
        font-size: 13px;
        line-height: 1.18;
      }

      /* ===== How mobile slider ===== */

      .landing-how-section {
        padding: 48px 0 104px;
      }

      .landing-how-bg--1 {
        left: -170px;
        top: 20px;
        width: 420px;
        opacity: .18;
      }

      .landing-how-bg--2 {
        right: -190px;
        top: 260px;
        width: 440px;
        opacity: .18;
      }

      .landing-how-title {
        grid-column: 1 / 3;

        margin-bottom: 24px;

        font-size: 32px;
        line-height: .98;
        text-align: center;
      }

      .landing-how-cards {
        grid-column: 1 / 3;

        display: flex;
        grid-template-columns: none;
        gap: 16px;

        overflow-x: auto;
        overflow-y: visible;

        padding: 4px 16px 22px;
        margin: 0 -16px;

        scroll-snap-type: x mandatory;
        scroll-padding-left: 16px;

        -webkit-overflow-scrolling: touch;
      }

      .landing-how-cards::-webkit-scrollbar {
        height: 6px;
      }

      .landing-how-cards::-webkit-scrollbar-track {
        background: rgba(23, 23, 53, .08);
        border-radius: 999px;
      }

      .landing-how-cards::-webkit-scrollbar-thumb {
        background: rgba(41, 32, 157, .45);
        border-radius: 999px;
      }

      .landing-how-card,
      .landing-how-card:first-child {
        grid-column: auto;

        flex: 0 0 calc(100% - 56px);

        min-height: 300px;
        height: auto;

        padding: 28px 22px 112px;

        border-radius: 32px;
        background: #fff;
        overflow: hidden;

        scroll-snap-align: start;

        box-shadow: 0 16px 28px rgba(22, 20, 70, .14);
      }

      .landing-how-card:hover {
        transform: none;
      }

      .landing-how-card__bg {
        display: none;
      }

      .landing-how-card h3 {
        margin-bottom: 16px;

        font-size: 26px;
        text-align: center;
      }

      .landing-how-card p {
        font-size: 15px;
        line-height: 1.25;
        text-align: center;
      }

      .landing-how-card__pic {
        right: 50%;
        bottom: 18px;

        transform: translateX(50%);
      }

      .landing-how-card__pic--book {
        width: 92px;
      }

      .landing-how-card__pic--pc {
        width: 92px;
      }

      .landing-how-card__pic--gamepad {
        width: 100px;
      }
    }

    /* ===== Очень маленькие телефоны ===== */

    @media (max-width: 420px) {
      .landing-container {
        width: calc(100% - 24px);
      }

      .login-header {
        bottom: 10px;
        width: calc(100% - 18px);
        height: 58px;
        padding: 0 12px;
      }

      .login-nav {
        left: 11px;
        right: 11px;
        bottom: 82px;
      }

      .login-header-btn {
        min-width: 96px;
        height: 34px;
        font-size: 14px;
      }

      .landing-hero {
        padding: 16px 0 104px;
      }

      .landing-brand {
        margin-bottom: 10px;
      }

      .landing-brand__title {
        font-size: 23px;
      }

      .landing-brand__subtitle {
        font-size: 11px;
      }

      .landing-hero__image {
        margin-bottom: 10px;
      }

      .landing-hero__image img {
        width: min(75vw, 285px);
      }

      .landing-hero h1 {
        max-width: 340px;
        font-size: 32px;
        line-height: 1;
      }

      .landing-hero p {
        max-width: 335px;
        margin: 14px 0 20px;

        font-size: 14px;
        line-height: 1.22;
      }

      .landing-play {
        max-width: 270px;
        height: 48px;
        font-size: 15px;
      }

      .landing-lopa-section {
        margin-top: -36px;
      }

      .landing-lopa-frame {
        border-radius: 28px;
      }

      .landing-lopa-list-grid {
        padding: 22px 14px;
      }

      .landing-lopa-list__item {
        grid-template-columns: 48px minmax(0, 1fr);
        gap: 12px;

        min-height: 76px;
        padding: 12px;
        border-radius: 18px;
      }

      .landing-lopa-list__item img {
        width: 48px;
        height: 48px;
      }

      .landing-lopa-list__item p {
        font-size: 12.5px;
      }

      .landing-how-title {
        font-size: 28px;
      }

      .landing-how-cards {
        gap: 12px;
        padding: 4px 12px 20px;
        margin: 0;
        scroll-padding-left: 12px;
      }

      .landing-how-card,
      .landing-how-card:first-child {
        flex-basis: calc(100% - 42px);

        min-height: 286px;
        padding: 24px 18px 104px;
        border-radius: 28px;
      }

      .landing-how-card h3 {
        font-size: 23px;
      }

      .landing-how-card p {
        font-size: 14px;
        line-height: 1.22;
      }

      .landing-how-card__pic--book {
        width: 84px;
      }

      .landing-how-card__pic--pc {
        width: 84px;
      }

      .landing-how-card__pic--gamepad {
        width: 92px;
      }
    }
  </style>
</head>

<body>
  <header class="login-header">
    <button class="mobile-menu-btn" type="button" aria-label="Открыть меню" onclick="toggleMobileMenu()">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <a href="index.php" class="login-logo" aria-label="Логотип">
      <img src="assets/img/logo.svg" alt="Логотип">
    </a>

    <nav class="login-nav" id="mobileMenu">
      <a href="#home">Главная</a>
      <a href="#lopa">О Лопе</a>
      <a href="#how">Как это работает?</a>

      <?php if ($role === 'teacher'): ?>
        <a href="teacher/dashboard.php">Панель учителя</a>
      <?php elseif ($role === 'student'): ?>
        <a href="student/home.php">Игры</a>
        <a href="student/profile.php">Профиль</a>
      <?php endif; ?>
    </nav>

    <?php if ($isLogged): ?>
      <a href="logout.php" class="login-header-btn">Выйти</a>
    <?php else: ?>
      <a href="login.php" class="login-header-btn">Войти</a>
    <?php endif; ?>
  </header>

  <main class="landing-page">

    <section class="landing-hero" id="home">

      <div class="landing-bg" aria-hidden="true">
        <img class="landing-bg__cloud landing-bg__cloud--1" src="assets/img/landing/Облако1.png" alt="">
        <img class="landing-bg__cloud landing-bg__cloud--2" src="assets/img/landing/Облако2.png" alt="">
        <img class="landing-bg__cloud landing-bg__cloud--3" src="assets/img/landing/Облако3.png" alt="">

        <img class="landing-bg__line landing-bg__line--1" src="assets/img/landing/Vector.svg" alt="">
        <img class="landing-bg__line landing-bg__line--2" src="assets/img/landing/Vector1.svg" alt="">

        <img class="landing-bg__star landing-bg__star--1" src="assets/img/landing/Звезда1.svg" alt="">
        <img class="landing-bg__star landing-bg__star--2" src="assets/img/landing/Звезда2.svg" alt="">
        <img class="landing-bg__star landing-bg__star--3" src="assets/img/landing/Звезда3.svg" alt="">
      </div>

      <div class="landing-container">
        <div class="landing-grid landing-hero__grid">

          <div class="landing-hero__content">
            <div class="landing-brand">
              <div class="landing-brand__title">Логопола</div>
              <div class="landing-brand__subtitle">игры, которые учат говорить</div>
            </div>

            <h1>Игры, которые<br>учат говорить</h1>

            <p>
              Интерактивные логопедические мини-игры для детей младших классов.
              Автоматизация звуков, развитие словаря, дыхательные упражнения
              и артикуляционная гимнастика в игровом формате
            </p>

            <a class="landing-play" href="<?= h($playHref) ?>">Играть</a>
          </div>

          <div class="landing-hero__image">
            <img src="assets/img/landing/MainLopa.png" alt="Лопа на острове">
          </div>

        </div>
      </div>

    </section>

    <section class="landing-lopa-section" id="lopa">
      <div class="landing-container">
        <div class="landing-grid">

          <div class="landing-lopa-frame">
            <img class="landing-lopa-frame__img" src="assets/img/landing/Frame.png" alt="Привет, меня зовут Лопа">

            <div class="landing-grid landing-lopa-list-grid">
              <div class="landing-lopa-list">

                <div class="landing-lopa-list__item">
                  <img src="assets/img/landing/ИконкаЛопа1.png" alt="">
                  <p>Лопа любит лопать буквы</p>
                </div>

                <div class="landing-lopa-list__item">
                  <img src="assets/img/landing/ИконкаЛопа2.png" alt="">
                  <p>У Лопы мягкий, пушистый животик, в котором живёт целая библиотека звуков</p>
                </div>

                <div class="landing-lopa-list__item">
                  <img src="assets/img/landing/ИконкаЛопа3.png" alt="">
                  <p>Лопа немного стесняется новых детей, но всегда машет лапкой первой</p>
                </div>

              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <section class="landing-how-section" id="how">
      <img class="landing-how-bg landing-how-bg--1" src="assets/img/landing/Vector3.svg" alt="">
      <img class="landing-how-bg landing-how-bg--2" src="assets/img/landing/Vector4.svg" alt="">

      <div class="landing-container">
        <div class="landing-grid">

          <h2 class="landing-how-title">Как это работает?</h2>

          <div class="landing-how-cards">

            <article class="landing-how-card">
              <img class="landing-how-card__bg" src="assets/img/landing/svg1.png" alt="">
              <div class="landing-how-card__content">
                <h3>Занятие</h3>
                <p>
                  Ребёнок проходит темы по методическим материалам на очных занятиях.
                  Осваиваются звуки, упражнения и речевые конструкции.
                </p>
              </div>
              <img class="landing-how-card__pic landing-how-card__pic--book" src="assets/img/landing/Книга.png" alt="">
            </article>

            <article class="landing-how-card">
              <img class="landing-how-card__bg" src="assets/img/landing/svg2.png" alt="">
              <div class="landing-how-card__content">
                <h3>Доступ</h3>
                <p>
                  Логопед создаёт профиль ученика и открывает игры,
                  соответствующие пройденной теме.
                </p>
              </div>
              <img class="landing-how-card__pic landing-how-card__pic--pc" src="assets/img/landing/Компьютер.png" alt="">
            </article>

            <article class="landing-how-card">
              <img class="landing-how-card__bg" src="assets/img/landing/svg3.png" alt="">
              <div class="landing-how-card__content">
                <h3>Закрепление</h3>
                <p>
                  Ребёнок играет дома и закрепляет материал в интерактивном формате.
                  Повторение в игровой форме помогает довести навык до автоматизма.
                </p>
              </div>
              <img class="landing-how-card__pic landing-how-card__pic--gamepad" src="assets/img/landing/Геймпад.png" alt="">
            </article>

          </div>

        </div>
      </div>
    </section>
  </main>

  <script>
    function toggleMobileMenu() {
      const menu = document.getElementById('mobileMenu');
      const button = document.querySelector('.mobile-menu-btn');

      if (!menu || !button) return;

      menu.classList.toggle('is-open');
      button.classList.toggle('is-open');
    }

    document.addEventListener('click', function (event) {
      const menu = document.getElementById('mobileMenu');
      const button = document.querySelector('.mobile-menu-btn');

      if (!menu || !button) return;

      const clickedInsideMenu = menu.contains(event.target);
      const clickedButton = button.contains(event.target);

      if (!clickedInsideMenu && !clickedButton) {
        menu.classList.remove('is-open');
        button.classList.remove('is-open');
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        const menu = document.getElementById('mobileMenu');
        const button = document.querySelector('.mobile-menu-btn');

        if (!menu || !button) return;

        menu.classList.remove('is-open');
        button.classList.remove('is-open');
      }
    });
  </script>
</body>

</html>