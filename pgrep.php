<?php

/**
 * Pgrep - A simple PHP application to search for a pattern in a file or directory
 * 
 * Usage: php search.php <pattern> <file or directory>
 * Algorithm: Knuth-Morris-Pratt (KMP) algorithm
 * 
 * @autor: Md Habibur Rahman
 */



// define global variable to count total searched files
$totalSearched = 0;

/**
 * Compute the longest prefix suffix (lps) array for the given pattern
 * 
 * @param string $pattern
 * @param array $lps
 */
function computeLPSArray($pattern, &$lps) {
    $len = 0;
    $i = 1;
    $lps[0] = 0;

    while ($i < strlen($pattern)) {
        if ($pattern[$i] == $pattern[$len]) {
            $len++;
            $lps[$i] = $len;
            $i++;
        } else {
            if ($len != 0) {
                $len = $lps[$len - 1];
            } else {
                $lps[$i] = 0;
                $i++;
            }
        }
    }
}

/**
 * Search for the given pattern in the given text using KMP algorithm
 * 
 * @param string $pattern
 * @param string $text
 * @param string $filename
 * @return array
 */
function searchKMP($pattern, $text, $filename = null) {
    $m = strlen($pattern);
    $n = strlen($text);

    $lps = array_fill(0, $m, 0);
    computeLPSArray($pattern, $lps);

    $matches = [];
    $i = $j = 0;
    while ($i < $n) {
        if ($pattern[$j] == $text[$i]) {
            $i++;
            $j++;
        }

        if ($j == $m) {
            $start = $i - $j;
            $end = $i;
            while ($start > 0 && $text[$start] != "\n") {
                $start--;
            }
            while ($end < $n && $text[$end] != "\n") { 
                $end++;
            }

            $line_number = substr_count(substr($text, 0, $start), "\n") + 2;

            if (array_key_exists($filename, $matches)) {
                $matches[$filename]['count']++;
                if (!in_array($line_number, array_column($matches[$filename]['lines'], 'line_number'))) {
                    $matches[$filename]['lines'][] = [
                        'line_number' => $line_number,
                        'matched_line' => substr($text, $start, $end - $start),
                    ];
                }
            } else {
                $matches[$filename] = [
                    'count' => 1,
                    'lines' => [
                        [
                            'line_number' => $line_number,
                            'matched_line' => substr($text, $start, $end - $start),
                        ]
                    ]
                ];
            }

            $j = $lps[$j - 1];
        } elseif ($i < $n && $pattern[$j] != $text[$i]) {
            if ($j != 0) {
                $j = $lps[$j - 1];
            } else {
                $i++;
            }
        }
    }

    return $matches;
}

function searchInFile($pattern, $filename) {
    $content = file_get_contents($filename);
    if ($content === false) {
        echo "Unable to read file: $filename\n";
        return;
    }

    return searchKMP($pattern, $content, $filename);
}

function searchInDirectory($pattern, $directory) {
    // Get all files and directories in the given directory, except hidden directories
    $files = glob($directory . '/*', GLOB_NOSORT);
    if ($files === false) {
        echo "Invalid directory: $directory\n";
        return;
    }

    $allMatches = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            global $totalSearched;
            $totalSearched++;
            $matches = searchInFile($pattern, $file);
            $allMatches = array_merge($allMatches, $matches);
        } elseif (is_dir($file)) {
            $matches = searchInDirectory($pattern, $file);
            $allMatches = array_merge($allMatches, $matches);
        }
    }

    return $allMatches;
}

// Handle command-line arguments
if ($argc < 3) {
    echo "\n\033[1;32mUsage:\033[0m pgrep <pattern> <file or directory>\n\n";
    echo "\033[1;32mExamples:\033[0m\n";
    echo "pgrep 'hello world' /path/to/file.txt\n";
    echo "pgrep 'hello world' /path/to/directory\n";
    exit(1);
}

$pattern = $argv[1];
$searchLocation = $argv[2];

if (is_file($searchLocation)) {
    $matches = searchInFile($pattern, $searchLocation);
} elseif (is_dir($searchLocation)) {
    $matches = searchInDirectory($pattern, $searchLocation);
} else {
    // Assume the input is text
    echo "Invalid input format\n";
}

if (empty($matches)) {
    echo "No matches found\n";
    exit(0);
}


if ($totalSearched == 0) {
    echo "No files found\n";
    exit(0);
}

echo "\n\033[1;32mSearched in total $totalSearched files\033[0m\n";

// Display matched results
foreach ($matches as $file => $match) {
    if (!empty($file)) {
        echo "\n\033[1;32m{$file}\033[0m \033[1;36m({$match['count']} matches)\033[0m\n";
    }
    foreach ($match['lines'] as $line) {
        echo "\033[1;33m{$line['line_number']}\033[0m: ".trim(preg_replace('/'.$pattern.'/i', "\033[1;31m$0\033[0m", $line['matched_line']))."\n";
    }
}
?>
