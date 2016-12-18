<?php
include 'properties.php';
include("lib/pChart2.1.4/class/pData.class.php");
include("lib/pChart2.1.4/class/pDraw.class.php");
include("lib/pChart2.1.4/class/pImage.class.php");
include("lib/pChart2.1.4/class/pPie.class.php");

$dest_db = mysqli_connect($dest_db_host, $dest_db_username, $dest_db_password, $dest_db_schema, $dest_db_port);

function execute_query(\mysqli $database, $query_string) {
    $query = mysqli_query($database, $query_string);

    if (!$query) {
        exit('MySQL Error! ' . mysqli_error($database) . PHP_EOL);
    };

    return $query;
}

function visual_price_for_movie($film_title, $dest_db) {
    $myData = new pData();

    $query_string = "SELECT migration_date, amount FROM rental_price where film_title = '$film_title'";
    $query = execute_query($dest_db, $query_string);

    $migration_dates = [];
    $amounts = [];
    while ($row = $query->fetch_array()) {
        $migration_dates[] = $row['migration_date'];
        $amounts[] = $row['amount'];
    }

    $myData->addPoints($migration_dates, "migration_date");
    $myData->addPoints($amounts, "amount");
    $myData->setAxisName(AXIS_Y, "Amount");
    $myData->setAxisName(AXIS_X, "Migration date");

    $height = 1280;
    $width = 720;
    $margin = 80;
    $myPicture = new pImage($height, $width, $myData);
    $myPicture->setFontProperties(array("FontName" => "lib/pChart2.1.4/fonts/Forgotte.ttf", "FontSize" => 24));
    $myPicture->setGraphArea($margin, $margin, $height - $margin, $width - $margin);
    $myPicture->drawScale();
    $myPicture->drawSplineChart();
    $myPicture->Render("price_changes.png");
}

function visual_rent_length($film_title, $dest_db) {
    $myData = new pData();
    $query_string = "SELECT start_date, end_date FROM rent where film_title = '$film_title'";
    $query = execute_query($dest_db, $query_string);
    $rent_length = [];
    while ($row = $query->fetch_array()) {
        if (strtotime($row['end_date']) > strtotime($row['start_date'])) {
            $rent_length[] = (strtotime($row['end_date']) - strtotime($row['start_date'])) / (60 * 60 * 24);
        }
    }
    $myData->addPoints($rent_length, "Rent");
    $myData->setAxisName(0, "Rental length");
    $myData->setAxisName(1, "Rent ID");

    $height = 1280;
    $width = 720;
    $margin = 80;
    $myPicture = new pImage($height, $width, $myData);
    $myPicture->setFontProperties(["FontName" => "lib/pChart2.1.4/fonts/Forgotte.ttf", "FontSize" => 24]);
    $myPicture->setGraphArea($margin, $margin, $height - $margin, $width - $margin);
    $myPicture->drawScale();
    $myPicture->drawSplineChart();
    $myPicture->Render("rents.png");
}

function visual_spring_films($dest_db) {
    $myData = new pData();

    $query_string = "
    SELECT
      category.name AS category,
      rent_count
    FROM (SELECT
            film_title,
            count(*) AS rent_count
          FROM rent
          WHERE month(start_date) >= 6
                AND month(start_date) <= 8
          GROUP BY film_title
         ) f
      JOIN film ON f.film_title = film.title
      JOIN category ON film.category_id = category.id
    GROUP BY category
    ORDER BY rent_count DESC
";
    $query = execute_query($dest_db, $query_string);

    $categories = [];
    $counters = [];
    while ($row = $query->fetch_array()) {
        $categories[] = $row['category'];
        $counters[] = $row['rent_count'];
    }

    $myData->addPoints($categories, "Category");
    $myData->addPoints($counters, "Count");
    $myData->setAbscissa("Category");

    /* Create the pChart object */
    $height = 1280;
    $width = 720;
    $myPicture = new pImage($height, $width, $myData, TRUE);

    /* Draw a solid background */
    $settings = array("R" => 173, "G" => 152, "B" => 217, "Dash" => 1, "DashR" => 193, "DashG" => 172, "DashB" => 237);
    $myPicture->drawFilledRectangle(0, 0, $height, $width, $settings);

    /* Draw a gradient overlay */
    $settings = array("StartR" => 209, "StartG" => 150, "StartB" => 231, "EndR" => 111, "EndG" => 3, "EndB" => 138, "Alpha" => 50);
    $myPicture->drawGradientArea(0, 0, $height, $width, DIRECTION_VERTICAL, $settings);

    /* Add a border to the picture */
    $myPicture->drawRectangle(0, 0, $height - 1, $width - 1, ["R" => 0, "G" => 0, "B" => 0]);

    /* Set the default font properties */
    $myPicture->setFontProperties(["FontName" => "lib/pChart2.1.4/fonts/Forgotte.ttf", "FontSize" => 24, "R" => 0, "G" => 0, "B" => 0]);

    /* Create the pPie object */
    $PieChart = new pPie($myPicture, $myData);

    /* Draw a splitted pie chart */
    $PieChart->draw3DPie($height / 2, $width / 2, [
        "Radius" => $height / 2 - 200,
        "WriteValues" => TRUE,
        "DataGapAngle" => 3,
        "DataGapRadius" => 1,
        "Border" => TRUE,
        "ValueR" => 0,
        "ValueG" => 0,
        "ValueB" => 0,
        "DrawLabels" => true,
        "LabelStacked" => true
    ]);

    $myPicture->Render("summer.png");
}

visual_price_for_movie('ACADEMY DINOSAUR', $dest_db);
visual_rent_length('ACADEMY DINOSAUR', $dest_db);
visual_spring_films($dest_db);
