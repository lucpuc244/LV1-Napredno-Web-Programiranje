
<?php

require_once "DiplomskiRadovi.php";

$radovi = new DiplomskiRadovi();

// Zadatak 1 - Dohvat podataka
$radovi->create();

// Zadatak 2 - Spremanje u bazu
$radovi->save();

// Zadatak 2 - Čitanje iz baze
$rezultati = $radovi->read();

echo "<h1>Diplomski radovi</h1>";

foreach ($rezultati as $rad) {

    echo "<h3>" .
        htmlspecialchars($rad["naziv_rada"]) .
        "</h3>";

    echo "<p><b>OIB tvrtke:</b> " .
        htmlspecialchars($rad["oib_tvrtke"] ?? "") .
        "</p>";

    echo "<p>" .
        htmlspecialchars($rad["tekst_rada"] ?? "") .
        "</p>";

    echo "<a href='" .
        htmlspecialchars($rad["link_rada"], ENT_QUOTES) .
        "' target='_blank'>Pogledaj rad</a>";

    echo "<hr>";
}
