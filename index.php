<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';
loadEnv(__DIR__ . '/.env');

$appID = getenv('API_KEY');
if (!$appID) {
    echo "Erreur : API_KEY introuvable dans le fichier .env";
    exit;
}
$language = "fr";

function getTankData($appID, $type, $language)
{
    $url = "https://api.worldoftanks.eu/wot/encyclopedia/vehicles/?application_id=" . $appID . "&fields=short_name%2C+tank_id&language=" . $language . "&tier=10&type=" . $type;
    $response = file_get_contents($url);
    return json_decode($response, TRUE)['data'];
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Chars par joueur - World of Tanks</title>
    <style>
        body {
            background-color: darkgrey;
        }

        table,
        th,
        td {
            border: 1px solid black;
            border-collapse: collapse;
            text-align: center;
            margin-bottom: 2em;
            margin-left: 2em;
            margin-top: 2em;
        }

        tr:hover {
            background-color: lightgray;
        }
    </style>
</head>

<body>
    <h3><a href="./index.php">Home</a></h3>
    <?php
    $json = file_get_contents('clans.json');
    $data = json_decode($json, true);
    $selectedClan = $data["clan"][0] || null;
    ?>

    <form method="post" action="">
        <label> Sélectionner le Clan : </label>
        <select id="selectclan" name="selectclan">
            <option value="" selected disabled>Choose Clan</option>
            <?php foreach ($data['clan'] as $clan): ?>
                <option value="<?= $clan; ?>" <?= $clan === $selectedClan ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($clan); ?>
                </option>
            <?php endforeach; ?>
        </select>
        OR
        <input type="text" id="inputclan" name="inputclan" placeholder="Your clan">
        : <input type="submit" name="submit" value="Afficher">
    </form>

    <br>

    <label>Liste des chars tier 10 : </label>
    <select id="selecttank" name="selecttank">
        <?php
        $tankTypes = [
            "lightTank" => "Chars Légers",
            "mediumTank" => "Chars Moyens",
            "heavyTank" => "Chars Lourds",
            "AT-SPG" => "TDs",
            "SPG" => "ARTAs (SKY CANCER)"
        ];

        foreach ($tankTypes as $key => $value) {
            echo '<option style="background-color:grey; color:white;" disabled>' . $value . '</option>';
            $tankData = getTankData($appID, $key, $language);
            foreach ($tankData as $short_name) {
                echo "<option value=" . '"' . $short_name['short_name'] . '|' . $short_name['tank_id'] . '">' . $short_name['short_name'] . ' </option>';
            }
        }
        ?>
    </select>

    <!-- Liste des chars / joueurs -->
    <?php
    if (isset($_POST['submit'])) {
        // DEBUG
        // var_dump($_POST);
        $nomclan = "";
        if (!empty($_POST['inputclan'])) {
            $nomclan = $_POST['inputclan'];
        } elseif (!empty($_POST['selectclan'])) {
            $nomclan = $_POST['selectclan'];
        }

        $check = 0;
        $nJoueurs = 0;

        $getclanidrequest = "https://api.worldoftanks.eu/wot/clans/list/?application_id=" . $appID . "&limit=1&search=" . $nomclan . "&fields=clan_id&language=" . $language . "";
        $getclanid = file_get_contents($getclanidrequest);
        $getclanid = json_decode($getclanid, TRUE);
        $clanid = $getclanid['data'][0]['clan_id'];

        $getAllPlayerInClan = "https://api.worldoftanks.eu/wot/clans/info/?application_id=" . $appID . "&clan_id=" . $clanid . "&fields=members_count%2C+members.account_id%2C+members.account_name";
        $getAllMembersName = file_get_contents($getAllPlayerInClan);
        $getAllMembersName = json_decode($getAllMembersName, TRUE);

        echo "<h1><b>Clan : " . $nomclan . "</b></h1>" . "\n";
        echo "<b> Nombre de joueurs dans le clan : " . $getAllMembersName['data'][$clanid]['members_count'] . " / 100 </b>";
        echo "<br>";

        // Récupération des chars de tous les types
        $tankTier10Array = [];
        foreach ($tankTypes as $key => $value) {
            $tankData = getTankData($appID, $key, $language);
            foreach ($tankData as $tankInfo) {
                $tankTier10Array[] = $tankInfo['tank_id'];
            }
        }

        $allTankInfoForAllPlayer = [];
        $allMemberNameArray = [];
        foreach ($getAllMembersName['data'][$clanid]['members'] as $member) {
            $nJoueurs++;
            $memberId = $member['account_id'];
            $memberName = $member['account_name'];
            $allMemberNameArray[$memberName] = $memberId;

            $getTankInfoPlayer = "https://api.worldoftanks.eu/wot/tanks/stats/?application_id=" . $appID . "&extra=random&fields=random.battles%2C+tank_id&account_id=" . $memberId . "&tank_id=" . implode(',', $tankTier10Array);
            $infoPlayer = file_get_contents($getTankInfoPlayer);
            $infoPlayer = json_decode($infoPlayer, TRUE);

            $allTankInfoForAllPlayer[$memberId] = $infoPlayer;
        }

        foreach ($tankTypes as $key => $value) {
            echo '<table name="tanklist">' . "\n";
            echo '<caption style="font-size:2em">' . $value . '</caption>';
            echo '<tr>' . "\n";
            echo '<th> Nom des joueurs </th>' . "\n";

            $getAllTankNameAndIdRequest = "https://api.worldoftanks.eu/wot/encyclopedia/vehicles/?application_id=" . $appID . "&tier=10&fields=short_name%2C+tank_id&type=" . $key;
            $getAllTankNameAndId = file_get_contents($getAllTankNameAndIdRequest);
            $getAllTankNameAndId = json_decode($getAllTankNameAndId, TRUE);

            $tankIdTable = [];
            foreach ($getAllTankNameAndId['data'] as $tankInfo) {
                $tankIdTable[] = $tankInfo['tank_id'];
                echo '<th style="padding:5px;">' . $tankInfo['short_name'] . '</th>' . "\n";
            }
            echo '</tr>' . "\n" . "\n";

            foreach ($allMemberNameArray as $key2 => $value2) {
                echo '<tr>';
                echo '<td>' . $key2 . '</td>';

                foreach ($tankIdTable as $tankId) {
                    $allPlayerTank = $allTankInfoForAllPlayer[$value2];
                    $found = false;
                    if (isset($allPlayerTank['data'][$value2])) {
                        foreach ($allPlayerTank['data'][$value2] as $playerTank) {
                            if ($playerTank['tank_id'] == $tankId) {
                                echo '<td style="background-color: #3ef825;"><b> X </b></td>';
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found) {
                        echo '<td> </td>';
                    }
                }
                echo '</tr>' . "\n";
            }
            echo '</table>' . "\n";
        }
    }
    ?>
</body>

</html>