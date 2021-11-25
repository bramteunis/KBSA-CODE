<?php
include "cartfuncties.php";
//include "browse.php";

$queryBuildResult = "";


if (isset($_GET['category_id'])) {
    $CategoryID = $_GET['category_id'];
} else {
    $CategoryID = "";
}
if (isset($_GET['products_on_page'])) {
    $ProductsOnPage = $_GET['products_on_page'];
    $_SESSION['products_on_page'] = $_GET['products_on_page'];
} else if (isset($_SESSION['products_on_page'])) {
    $ProductsOnPage = $_SESSION['products_on_page'];
} else {
    $ProductsOnPage = 25;
    $_SESSION['products_on_page'] = 25;
}
if (isset($_GET['page_number'])) {
    $PageNumber = $_GET['page_number'];
} else {
    $PageNumber = 0;
}

// code deel 1 van User story: Zoeken producten
// <voeg hier de code in waarin de zoekcriteria worden opgebouwd>
$SearchString = "";

if (isset($_GET['search_string'])) {
    $SearchString = $_GET['search_string'];
}
if (isset($_GET['sort'])) {
    $SortOnPage = $_GET['sort'];
    $_SESSION["sort"] = $_GET['sort'];
} else if (isset($_SESSION["sort"])) {
    $SortOnPage = $_SESSION["sort"];
} else {
    $SortOnPage = "price_low_high";
    $_SESSION["sort"] = "price_low_high";
}

switch ($SortOnPage) {
    case "price_high_low":
    {
        $Sort = "SellPrice DESC";
        break;
    }
    case "name_low_high":
    {
        $Sort = "StockItemName";
        break;
    }
    case "name_high_low";
        $Sort = "StockItemName DESC";
        break;
    case "price_low_high":
    {
        $Sort = "SellPrice";
        break;
    }
    default:
    {
        $Sort = "SellPrice";
        $SortName = "price_low_high";
    }
}
$searchValues = explode(" ", $SearchString);

$queryBuildResult = "";
if ($SearchString != "") {
    for ($i = 0; $i < count($searchValues); $i++) {
        if ($i != 0) {
            $queryBuildResult .= "AND ";
        }
        $queryBuildResult .= "SI.SearchDetails LIKE '%$searchValues[$i]%' ";
    }
    if ($queryBuildResult != "") {
        $queryBuildResult .= " OR ";
    }
    if ($SearchString != "" || $SearchString != null) {
        $queryBuildResult .= "SI.StockItemID ='$SearchString'";
    }
}



// <einde van de code voor zoekcriteria>
// einde code deel 1 van User story: Zoeken producten


$Offset = $PageNumber * $ProductsOnPage;

if ($CategoryID != "") { 
    if ($queryBuildResult != "") {
    $queryBuildResult .= " AND ";
    }
}

// code deel 2 van User story: Zoeken producten
// <voeg hier de code in waarin het zoekresultaat opgehaald wordt uit de database>
if ($CategoryID == "") {
    if ($queryBuildResult != "") {
        $queryBuildResult = "WHERE " . $queryBuildResult;
    }

    $Query = "
                SELECT SI.StockItemID, SI.StockItemName, SI.MarketingComments, TaxRate, RecommendedRetailPrice, ROUND(TaxRate * RecommendedRetailPrice / 100 + RecommendedRetailPrice,2) as SellPrice,
                QuantityOnHand,
                (SELECT ImagePath
                FROM stockitemimages
                WHERE StockItemID = SI.StockItemID LIMIT 1) as ImagePath,
                (SELECT ImagePath FROM stockgroups JOIN stockitemstockgroups USING(StockGroupID) WHERE StockItemID = SI.StockItemID LIMIT 1) as BackupImagePath
                FROM stockitems SI
                JOIN stockitemholdings SIH USING(stockitemid)
                " . $queryBuildResult . "
                GROUP BY StockItemID
                ORDER BY " . $Sort . "
                LIMIT ?  OFFSET ?";


    $Statement = mysqli_prepare($databaseConnection, $Query);
    mysqli_stmt_bind_param($Statement, "ii",  $ProductsOnPage, $Offset);
    mysqli_stmt_execute($Statement);
    $ReturnableResult = mysqli_stmt_get_result($Statement);
    $ReturnableResult = mysqli_fetch_all($ReturnableResult, MYSQLI_ASSOC);

    $Query = "
            SELECT count(*)
            FROM stockitems SI
            $queryBuildResult";
    $Statement = mysqli_prepare($databaseConnection, $Query);
    mysqli_stmt_execute($Statement);
    $Result = mysqli_stmt_get_result($Statement);
    $Result = mysqli_fetch_all($Result, MYSQLI_ASSOC);
}


// <einde van de code voor zoekresultaat>
// einde deel 2 van User story: Zoeken producten

if ($CategoryID !== "") {
$Query = "
           SELECT SI.StockItemID, SI.StockItemName, SI.MarketingComments, TaxRate, RecommendedRetailPrice,
           ROUND(SI.TaxRate * SI.RecommendedRetailPrice / 100 + SI.RecommendedRetailPrice,2) as SellPrice,
           QuantityOnHand,
           (SELECT ImagePath FROM stockitemimages WHERE StockItemID = SI.StockItemID LIMIT 1) as ImagePath,
           (SELECT ImagePath FROM stockgroups JOIN stockitemstockgroups USING(StockGroupID) WHERE StockItemID = SI.StockItemID LIMIT 1) as BackupImagePath
           FROM stockitems SI
           JOIN stockitemholdings SIH USING(stockitemid)
           JOIN stockitemstockgroups USING(StockItemID)
           JOIN stockgroups ON stockitemstockgroups.StockGroupID = stockgroups.StockGroupID
           WHERE " . $queryBuildResult . " ? IN (SELECT StockGroupID from stockitemstockgroups WHERE StockItemID = SI.StockItemID)
           GROUP BY StockItemID
           ORDER BY " . $Sort . "
           LIMIT ? OFFSET ?";

    $Statement = mysqli_prepare($databaseConnection, $Query);
    mysqli_stmt_bind_param($Statement, "iii", $CategoryID, $ProductsOnPage, $Offset);
    mysqli_stmt_execute($Statement);
    $ReturnableResult = mysqli_stmt_get_result($Statement);
    $ReturnableResult = mysqli_fetch_all($ReturnableResult, MYSQLI_ASSOC);

    $Query = "
                SELECT count(*)
                FROM stockitems SI
                WHERE " . $queryBuildResult . " ? IN (SELECT SS.StockGroupID from stockitemstockgroups SS WHERE SS.StockItemID = SI.StockItemID)";
    $Statement = mysqli_prepare($databaseConnection, $Query);
    mysqli_stmt_bind_param($Statement, "i", $CategoryID);
    mysqli_stmt_execute($Statement);
    $Result = mysqli_stmt_get_result($Statement);
    $Result = mysqli_fetch_all($Result, MYSQLI_ASSOC);
}
    function berekenVerkoopPrijs($adviesPrijs, $btw) {
		return $btw * $adviesPrijs / 100 + $adviesPrijs;
    }

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Winkelwagen</title>
    <link rel="stylesheet" href="public/css/style.css" type="text/css">
</head>
<body>
<h1>Inhoud Winkelwagen</h1>

<?php

include __DIR__ . "/header.php";

$cart = getCart();
foreach($cart as $artikelnummer => $aantalartikel){
    if($aantalartikel > 0){
        $StockItem = getStockItem($artikelnummer, $databaseConnection);
        $StockItemImage = getStockItemImage($artikelnummer, $databaseConnection);
        print ("<h1 style='color:black;'>".$StockItem['StockItemName']."</h1>");
        print ("<img src="."public/stockitemimg/".str_replace(" ", "%20",strtolower($StockItemImage[0]['ImagePath'])).">");
        
        //print ("<h1 class='StockItemPriceText'>".'€'.sprintf('%0.2f', berekenVerkoopPrijs($row['RecommendedRetailPrice'], $row['TaxRate']))."</h1>");
        //print($_SESSION['prijs']);
        
        if (isset($ReturnableResult) && count($ReturnableResult) > 0) {
            foreach ($ReturnableResult as $row) {
                print ("<h1 class='StockItemPriceText'>".'€'.sprintf('%0.2f', berekenVerkoopPrijs($row['RecommendedRetailPrice'], $row['TaxRate']))."</h1>");
            }
        }
        print('<form method="post">');
        print('<input type="number" name="stockItemID" value="print($artikelnummer)" hidden>');
        print('<input class="ToevoegenWinkelmandbutton ToevoegenWinkelmandbutton1" type="submit" name="submit" value="Verwijderen uit winkelmandje">');
        print('</form>');

        if (isset($_POST["submit"])) {              // zelfafhandelend formulier
            $stockItemID = $artikelnummer;
            removeProductFromCart($stockItemID);         // maak gebruik van geïmporteerde functie uit cartfuncties.php
        }
    }
}
print_r($cart);

?>
<p><a href='view.php?id=0'>Naar artikelpagina van artikel 0</a></p>
</body>
</html>
