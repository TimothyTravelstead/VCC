<div id="Categories" class="CategoriesTop">
    <form id="Menus" action="" method="post">
        <div>
            <select name="All" class="normalMenu" aria-label="All Categories">
                <option value="All">ALL</option>

                <?php
                    // Build Category Menu Selectors from Database List    
                    $query = "SELECT mytype, Color FROM Types ORDER BY mytype";
                    $result = dataQuery($query);

                    // Define the Arrays to hold the Category Menu Items
                    $myType = array();
                    $myColor = array();
                    $mainName = array();
                    $Count = 1;
                    $PriorCount = $Count - 1;

                    foreach ($result as $row) {
                        $myType[$Count] = $row->mytype;
                        $myColor[$Count] = $row->Color;
                        $mainName[$Count] = strstr($myType[$Count], "-", true);

                        $myAppearance = $myColor[$Count] == "1" 
                            ? " class='highlightMenu'" 
                            : " class='normalMenu'";

                        if (!$mainName[$Count]) {
                            $mainName[$Count] = $myType[$Count];
                        }

                        if (isset($mainName[$PriorCount]) && $mainName[$Count] != $mainName[$PriorCount]) {
                            echo "</select><select name='" . htmlspecialchars($mainName[$Count]) . "'" . $myAppearance . " aria-label='" . htmlspecialchars($mainName[$Count]) . " Category'>";
                            echo "<option value='" . htmlspecialchars($mainName[$Count]) . "'>" .
                                 htmlspecialchars(strtoupper($mainName[$Count])) . "</option>";
                        }

                        if ($mainName[$Count] != $myType[$Count]) {
                            echo "<option value='" . htmlspecialchars($myType[$Count]) . "'>" . 
                                 htmlspecialchars($myType[$Count]) . "</option>";
                        }

                        $Count++;
                        $PriorCount = $Count - 1;
                    }
                ?>
            </select>
        </div>
    </form>
</div>
