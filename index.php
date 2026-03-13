<?php
require_once 'class/config.php';
require_once 'class/database.php';
require_once 'steamauth/steamauth.php';
require_once 'class/utils.php';

$db = new DataBase();

if (isset($_SESSION['steamid'])) {
    $steamid = $_SESSION['steamid'];

    $weapons = UtilsClass::getWeaponsFromArray();
    $skins = UtilsClass::skinsFromJson();
    $knifes = UtilsClass::getKnifeTypes();

    $querySelected = $db->select("
        SELECT `weapon_defindex`, MAX(`weapon_paint_id`) AS `weapon_paint_id`, MAX(`weapon_wear`) AS `weapon_wear`, MAX(`weapon_seed`) AS `weapon_seed`
        FROM `wp_player_skins`
        WHERE `steamid` = :steamid
        GROUP BY `weapon_defindex`, `steamid`
    ", ["steamid" => $steamid]);

    $selectedSkins = UtilsClass::getSelectedSkins($querySelected);
    $selectedKnife = $db->select(
        "SELECT * FROM `wp_player_knife` WHERE `wp_player_knife`.`steamid` = :steamid LIMIT 1",
        ["steamid" => $steamid]
    );

    $weaponCategories = [
        'all' => [],
        'knives' => [500, 503, 505, 506, 507, 508, 509, 512, 514, 515, 516, 517, 518, 519, 520, 521, 522, 523, 525],
        'pistols' => [1, 2, 3, 4, 30, 32, 36, 61, 63, 64],
        'rifles' => [7, 8, 10, 13, 16, 39, 60],
        'snipers' => [9, 11, 38, 40],
        'smgs' => [17, 19, 23, 24, 26, 33, 34],
        'heavy' => [14, 25, 27, 28, 29],
    ];

    $currentCategory = $_GET['category'] ?? 'all';
    if (!array_key_exists($currentCategory, $weaponCategories)) {
        $currentCategory = 'all';
    }

    if (isset($_POST['forma'])) {
        $ex = explode("-", $_POST['forma']);

        if ($ex[0] == "knife") {
            $db->query(
                "INSERT INTO `wp_player_knife` (`steamid`, `knife`, `weapon_team`) VALUES(:steamid, :knife, 2)
                 ON DUPLICATE KEY UPDATE `knife` = :knife",
                ["steamid" => $steamid, "knife" => $knifes[$ex[1]]['weapon_name']]
            );

            $db->query(
                "INSERT INTO `wp_player_knife` (`steamid`, `knife`, `weapon_team`) VALUES(:steamid, :knife, 3)
                 ON DUPLICATE KEY UPDATE `knife` = :knife",
                ["steamid" => $steamid, "knife" => $knifes[$ex[1]]['weapon_name']]
            );
        } else {
            if (
                isset($skins[$ex[0]]) &&
                array_key_exists($ex[1], $skins[$ex[0]]) &&
                isset($_POST['wear']) &&
                $_POST['wear'] >= 0.00 &&
                $_POST['wear'] <= 1.00 &&
                isset($_POST['seed'])
            ) {
                $wear = floatval($_POST['wear']);
                $seed = intval($_POST['seed']);

                if (array_key_exists($ex[0], $selectedSkins)) {
                    $db->query(
                        "UPDATE wp_player_skins
                         SET weapon_paint_id = :weapon_paint_id, weapon_wear = :weapon_wear, weapon_seed = :weapon_seed
                         WHERE steamid = :steamid AND weapon_defindex = :weapon_defindex",
                        [
                            "steamid" => $steamid,
                            "weapon_defindex" => $ex[0],
                            "weapon_paint_id" => $ex[1],
                            "weapon_wear" => $wear,
                            "weapon_seed" => $seed
                        ]
                    );
                } else {
                    $db->query(
                        "INSERT INTO wp_player_skins (`steamid`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`, `weapon_team`)
                         VALUES (:steamid, :weapon_defindex, :weapon_paint_id, :weapon_wear, :weapon_seed, 2)",
                        [
                            "steamid" => $steamid,
                            "weapon_defindex" => $ex[0],
                            "weapon_paint_id" => $ex[1],
                            "weapon_wear" => $wear,
                            "weapon_seed" => $seed
                        ]
                    );

                    $db->query(
                        "INSERT INTO wp_player_skins (`steamid`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`, `weapon_team`)
                         VALUES (:steamid, :weapon_defindex, :weapon_paint_id, :weapon_wear, :weapon_seed, 3)",
                        [
                            "steamid" => $steamid,
                            "weapon_defindex" => $ex[0],
                            "weapon_paint_id" => $ex[1],
                            "weapon_wear" => $wear,
                            "weapon_seed" => $seed
                        ]
                    );
                }
            }
        }

        $redirectCategory = urlencode($currentCategory);
        header("Location: {$_SERVER['PHP_SELF']}?category={$redirectCategory}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" <?php if (WEB_STYLE_DARK) echo 'data-bs-theme="dark"'; ?>>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CS2 Simple Weapon Paints</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php if (!isset($_SESSION['steamid'])): ?>
    <div class="bg-primary app-topbar">
        <h2>
            <span>To choose weapon paints loadout, you need to</span>
            <?php loginbutton("rectangle"); ?>
        </h2>
    </div>
<?php else: ?>

    <div class="bg-primary app-topbar">
        <h2>
            <span>Your current weapon skin loadout</span>
            <a class="btn btn-danger" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout">Logout</a>
        </h2>
    </div>

    <div class="category-menu-wrap">
        <a class="category-btn <?php echo ($currentCategory == 'all') ? 'active' : ''; ?>" href="?category=all">All Weapons</a>
        <a class="category-btn <?php echo ($currentCategory == 'knives') ? 'active' : ''; ?>" href="?category=knives">Knives</a>
        <a class="category-btn <?php echo ($currentCategory == 'rifles') ? 'active' : ''; ?>" href="?category=rifles">Rifles</a>
        <a class="category-btn <?php echo ($currentCategory == 'smgs') ? 'active' : ''; ?>" href="?category=smgs">SMGs</a>
        <a class="category-btn <?php echo ($currentCategory == 'snipers') ? 'active' : ''; ?>" href="?category=snipers">Snipers</a>
        <a class="category-btn <?php echo ($currentCategory == 'pistols') ? 'active' : ''; ?>" href="?category=pistols">Pistols</a>
        <a class="category-btn <?php echo ($currentCategory == 'heavy') ? 'active' : ''; ?>" href="?category=heavy">Heavy</a>
    </div>

    <div class="card-group mt-2">

        <?php if ($currentCategory == 'all' || $currentCategory == 'knives'): ?>
            <div class="col-sm-2">
                <div class="card text-center mb-3 border border-primary special-knife-card">
                    <div class="card-body">
                        <?php
                        $actualKnife = $knifes[0];
                        if ($selectedKnife != null) {
                            foreach ($knifes as $knife) {
                                if ($selectedKnife[0]['knife'] == $knife['weapon_name']) {
                                    $actualKnife = $knife;
                                    break;
                                }
                            }
                        }

                        echo "<div class='card-header'>";
                        echo "<h6 class='card-title item-name'>Knife type</h6>";
                        echo "<h5 class='card-title item-name'>{$actualKnife['paint_name']}</h5>";
                        echo "</div>";
                        echo "<img src='{$actualKnife['image_url']}' class='skin-image' alt='Knife'>";
                        ?>
                    </div>
                    <div class="card-footer">
                        <form action="?category=<?php echo urlencode($currentCategory); ?>" method="POST">
                            <select name="forma" class="form-control select" onchange="this.form.submit()">
                                <option disabled>Select knife</option>
                                <?php foreach ($knifes as $knifeKey => $knife): ?>
                                    <option
                                        value="knife-<?php echo $knifeKey; ?>"
                                        <?php echo (isset($selectedKnife[0]['knife']) && $selectedKnife[0]['knife'] == $knife['weapon_name']) ? 'selected' : ''; ?>>
                                        <?php echo $knife['paint_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($weapons as $defindex => $default): ?>

            <?php
            if (in_array((int)$defindex, $weaponCategories['knives'])) {
                continue;
            }

            if ($currentCategory !== 'all') {
                if (!in_array((int)$defindex, $weaponCategories[$currentCategory])) {
                    continue;
                }
            }

            $selectedSkinInfo = isset($selectedSkins[$defindex]) ? $selectedSkins[$defindex] : null;
            $queryWear = $selectedSkins[$defindex]['weapon_wear'] ?? 1.0;
            $initialWearValue = isset($selectedSkinInfo['weapon_wear']) ? $selectedSkinInfo['weapon_wear'] : 0.0;
            $initialSeedValue = isset($selectedSkinInfo['weapon_seed']) ? $selectedSkinInfo['weapon_seed'] : 0;
            ?>

            <div class="col-sm-2">
                <div class="card text-center mb-3">
                    <div class="card-body">
                        <?php if (array_key_exists($defindex, $selectedSkins)): ?>
                            <div class='card-header'>
                                <h5 class='card-title item-name'>
                                    <?php echo $skins[$defindex][$selectedSkins[$defindex]['weapon_paint_id']]['paint_name']; ?>
                                </h5>
                            </div>
                            <img src="<?php echo $skins[$defindex][$selectedSkins[$defindex]['weapon_paint_id']]['image_url']; ?>" class="skin-image" alt="Skin">
                        <?php else: ?>
                            <div class='card-header'>
                                <h5 class='card-title item-name'><?php echo $default['paint_name']; ?></h5>
                            </div>
                            <img src="<?php echo $default['image_url']; ?>" class="skin-image" alt="Default Skin">
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <form action="?category=<?php echo urlencode($currentCategory); ?>" method="POST">
                            <select name="forma" class="form-control select" onchange="this.form.submit()">
                                <option disabled>Select skin</option>
                                <?php foreach ($skins[$defindex] as $paintKey => $paint): ?>
                                    <option
                                        value="<?php echo $defindex . '-' . $paintKey; ?>"
                                        <?php echo (array_key_exists($defindex, $selectedSkins) && $selectedSkins[$defindex]['weapon_paint_id'] == $paintKey) ? 'selected' : ''; ?>>
                                        <?php echo $paint['paint_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <br>

                            <?php if ($selectedSkinInfo): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#weaponModal<?php echo $defindex; ?>">
                                    Settings
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="showSkinSelectionAlert()">
                                    Settings
                                </button>
                            <?php endif; ?>

                            <div class="modal fade" id="weaponModal<?php echo $defindex; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <?php
                                                if (array_key_exists($defindex, $selectedSkins)) {
                                                    echo $skins[$defindex][$selectedSkins[$defindex]['weapon_paint_id']]['paint_name'] . " Settings";
                                                } else {
                                                    echo $default['paint_name'] . " Settings";
                                                }
                                                ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="form-group mb-3">
                                                <select class="form-select" id="wearSelect<?php echo $defindex; ?>" onchange="updateWearValue<?php echo $defindex; ?>(this.value)">
                                                    <option disabled>Select Wear</option>
                                                    <option value="0.00" <?php echo ($initialWearValue == 0.00) ? 'selected' : ''; ?>>Factory New</option>
                                                    <option value="0.07" <?php echo ($initialWearValue == 0.07) ? 'selected' : ''; ?>>Minimal Wear</option>
                                                    <option value="0.15" <?php echo ($initialWearValue == 0.15) ? 'selected' : ''; ?>>Field-Tested</option>
                                                    <option value="0.38" <?php echo ($initialWearValue == 0.38) ? 'selected' : ''; ?>>Well-Worn</option>
                                                    <option value="0.45" <?php echo ($initialWearValue == 0.45) ? 'selected' : ''; ?>>Battle-Scarred</option>
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="wear<?php echo $defindex; ?>">Wear:</label>
                                                        <input type="text" value="<?php echo $initialWearValue; ?>" class="form-control" id="wear<?php echo $defindex; ?>" name="wear">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="seed<?php echo $defindex; ?>">Seed:</label>
                                                        <input type="text" value="<?php echo $initialSeedValue; ?>" class="form-control" id="seed<?php echo $defindex; ?>" name="seed" oninput="validateSeed(this)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-danger">Use</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function updateWearValue<?php echo $defindex; ?>(selectedValue) {
                    document.getElementById("wear<?php echo $defindex; ?>").value = selectedValue;
                }
            </script>

        <?php endforeach; ?>
    </div>

<?php endif; ?>

<div class="container">
    <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
        <div class="col-md-4 d-flex align-items-center">
            <span class="mb-3 mb-md-0 text-body-secondary">© 2023 <a href="https://github.com/Nereziel/cs2-WeaponPaints">Nereziel/cs2-WeaponPaints</a></span>
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showSkinSelectionAlert() {
        alert("You need to select a skin first.");
    }

    function validateSeed(input) {
        var inputValue = input.value.replace(/[^0-9]/g, '');

        if (inputValue === "") {
            input.value = 0;
        } else {
            var numericValue = parseInt(inputValue);
            numericValue = Math.min(1000, Math.max(1, numericValue));
            input.value = numericValue;
        }
    }
</script>
</body>
</html>
