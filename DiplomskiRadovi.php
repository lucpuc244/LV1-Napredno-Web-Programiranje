
<?php

require_once "iRadovi.php";

class DiplomskiRadovi implements iRadovi {

    public $naziv_rada;
    public $tekst_rada;
    public $link_rada;
    public $oib_tvrtke;

    private $pdo;
    private $radovi = [];

    // Konstruktor - povezivanje s MySQL bazom
    public function __construct(?PDO $pdo = null) {

        if ($pdo !== null) {
            $this->pdo = $pdo;
            return;
        }

        $this->pdo = new PDO(
            "mysql:host=localhost;dbname=radovi;charset=utf8mb4",
            "root",
            ""
        );

        $this->pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    // Dohvat HTML sadržaja pomoću cURL-a
    private function dohvatiHTML($url) {

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => "Mozilla/5.0"
        ]);

        $html = curl_exec($ch);

        if ($html === false) {
            curl_close($ch);
            return false;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $status === 200 ? $html : false;
    }

    // Pretvaranje HTML sadržaja u DOMXPath
    private function napraviXPath($html) {

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html
        );

        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    // ZADATAK 1 - CREATE
    // Dohvacanje radova sa stranica 2 do 6
    public function create() {

        $this->radovi = [];

        for ($i = 2; $i <= 6; $i++) {

            $url = "https://stup.ferit.hr/index.php/zavrsni-radovi/page/$i/";

            $html = $this->dohvatiHTML($url);

            if (!$html) {
                echo "Neuspjesan dohvat stranice $i<br>";
                continue;
            }

            $xpath = $this->napraviXPath($html);

            // Pronalazak svih clanaka
            $clanci = $xpath->query(
                "//article[contains(@class, 'post')]"
            );

            foreach ($clanci as $clanak) {

                // Dohvat naslova i poveznice
                $naslovNode = $xpath->query(
                    ".//h2[contains(@class, 'entry-title')]/a",
                    $clanak
                )->item(0);

                if (!$naslovNode) {
                    continue;
                }

                $naziv = trim($naslovNode->textContent);

                $link = $naslovNode->getAttribute("href");

                // Dohvat slike iz koje se izdvaja OIB
                $slikaNode = $xpath->query(
                    ".//img",
                    $clanak
                )->item(0);

                $oib = "";

                if ($slikaNode) {

                    $src = $slikaNode->getAttribute("src");

                    // OIB je 11-znamenkasti broj
                    if (preg_match(
                        '/(?:^|\/)(\d{11})(?:[-.])/i',
                        $src,
                        $matches
                    )) {
                        $oib = $matches[1];
                    }
                }

                // Dohvat teksta s poveznice rada
                $tekst = "";

                $htmlRada = $this->dohvatiHTML($link);

                if ($htmlRada) {

                    $xpathRada = $this->napraviXPath(
                        $htmlRada
                    );

                    $tekstNode = $xpathRada->query(
                        "//div[contains(@class, 'post-content-container')]"
                    )->item(0);

                    if (!$tekstNode) {

                        $tekstNode = $xpathRada->query(
                            "//div[contains(@class, 'post-content')]"
                        )->item(0);
                    }

                    if ($tekstNode) {

                        $tekst = trim(
                            preg_replace(
                                '/\s+/u',
                                ' ',
                                $tekstNode->textContent
                            )
                        );
                    }
                }

                // Stvaranje objekta klase DiplomskiRadovi
                $rad = new DiplomskiRadovi($this->pdo);

                // Spremanje podataka u objekt
                $rad->naziv_rada = $naziv;
                $rad->tekst_rada = $tekst;
                $rad->link_rada = $link;
                $rad->oib_tvrtke = $oib;

                // Dodavanje objekta u polje
                $this->radovi[] = $rad;
            }
        }

        echo "Dohvaceno radova: "
            . count($this->radovi) . "<br>";
    }

    // ZADATAK 2 - SAVE
    // Spremanje radova u MySQL bazu
    public function save() {

        $sql = "INSERT INTO diplomski_radovi
                (naziv_rada, tekst_rada, link_rada, oib_tvrtke)
                VALUES
                (:naziv, :tekst, :link, :oib)
                ON DUPLICATE KEY UPDATE
                naziv_rada = VALUES(naziv_rada),
                tekst_rada = VALUES(tekst_rada),
                oib_tvrtke = VALUES(oib_tvrtke)";

        $stmt = $this->pdo->prepare($sql);

        foreach ($this->radovi as $rad) {

            $stmt->execute([
                ":naziv" => $rad->naziv_rada,
                ":tekst" => $rad->tekst_rada,
                ":link" => $rad->link_rada,
                ":oib" => $rad->oib_tvrtke
            ]);
        }

        echo "Radovi su spremljeni u bazu.<br>";
    }

    // ZADATAK 2 - READ
    // Citanje svih radova iz MySQL baze
    public function read() {

        $stmt = $this->pdo->query(
            "SELECT * FROM diplomski_radovi"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
