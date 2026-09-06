<?php
// views/layouts/header.php
// Expects: $pageTitle (string)
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Dashboard') ?> – <?= APP_NAME ?></title>
  <meta name="description" content="<?= APP_NAME ?> – Digital Chain-of-Custody Tracking System">
  <link rel="stylesheet" href="<?= (defined('BASE_PATH') ? BASE_PATH : '') ?>/public/css/chaintrack.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
