<div id="Categories" class="CategoriesTop">
    <form id="Menus" action="" method="post">
        <div><button type='button' class='category-button' data-category='All'>ALL</button><?php
                // Build Category Menu Selectors from Database List
                $query = "SELECT mytype, Color FROM Types ORDER BY mytype";
                $result = dataQuery($query);

                // First pass: Group types by main category
                $categories = array();
                foreach ($result as $row) {
                    $myType = $row->mytype;
                    $myColor = $row->Color;

                    // Skip empty/invalid entries
                    if (empty(trim($myType))) {
                        continue;
                    }

                    $mainName = strstr($myType, "-", true);
                    if (!$mainName) {
                        $mainName = $myType;
                    }

                    if (!isset($categories[$mainName])) {
                        $categories[$mainName] = array(
                            'color' => $myColor,
                            'subtypes' => array()
                        );
                    }

                    // Only add as subtype if it's different from the main name
                    if ($mainName != $myType) {
                        $categories[$mainName]['subtypes'][] = $myType;
                    }
                }

                // Second pass: Output buttons for single-option categories, selects for multi-option
                foreach ($categories as $mainName => $data) {
                    // Skip empty category names
                    if (empty(trim($mainName))) {
                        continue;
                    }

                    $myAppearance = $data['color'] == "1" ? "highlightMenu" : "normalMenu";

                    if (count($data['subtypes']) == 0) {
                        // Single option - use just a button (no wrapper needed)
                        // Abbreviate long category names for display
                        $displayName = $mainName;
                        if ($mainName == "Community Center") {
                            $displayName = "Comm Ctr";
                        }
                        echo "<button type='button' class='" . $myAppearance . " category-button' data-category='" .
                             htmlspecialchars($mainName) . "'>" .
                             htmlspecialchars(strtoupper($displayName)) . "</button>";
                    } else {
                        // Multiple options - use wrapper with trigger button and select
                        echo "<div class='category-wrapper'>";
                        echo "<button type='button' class='category-trigger' data-category='" .
                             htmlspecialchars($mainName) . "'></button>";
                        echo "<select name='" . htmlspecialchars($mainName) . "' class='" . $myAppearance .
                             "' aria-label='" . htmlspecialchars($mainName) . " Category'>";
                        echo "<option value='" . htmlspecialchars($mainName) . "'>" .
                             htmlspecialchars(strtoupper($mainName)) . "</option>";

                        foreach ($data['subtypes'] as $subtype) {
                            echo "<option value='" . htmlspecialchars($subtype) . "'>" .
                                 htmlspecialchars($subtype) . "</option>";
                        }

                        echo "</select>";
                        echo "</div>";
                    }
                }
            ?></div>
    </form>
</div>
