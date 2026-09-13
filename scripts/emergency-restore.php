<?php
/**
 * Doomsday Failsafe: Emergency Restore Script (Stateful & Chunked)
 * 
 * Usage:
 * 1. Upload all files from your snapshot zip to the server.
 * 2. Update wp-config.php with your new database credentials if they changed.
 * 3. Navigate to http://yoursite.com/emergency-restore.php
 */

// SECURITY HARDENING: Prevent execution if still inside the plugins directory
if ( strpos( __FILE__, 'wp-content/plugins' ) !== false || strpos( __FILE__, 'wp-content\plugins' ) !== false ) {
    die( 'Security restriction: This emergency restore script cannot be executed from within the WordPress plugins directory. Please move it to the root of your WordPress installation.' );
}

error_reporting(0);
// Batch 1 fixes
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M'); // Batch 4: Prevent OOM on weak servers
@ini_set('pcre.backtrack_limit', 5000000); // Batch 6: Prevent silent regex wipeouts on massive strings
@set_time_limit(0); // Batch 7: Prevent PHP fatal timeout on massive ZIP extraction

// Helper function to locate wp-config.php securely
function get_wp_config_path() {
    $search_dirs = [dirname(__FILE__)];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $search_dirs[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    }

    foreach ($search_dirs as $dir) {
        for ($i = 0; $i < 6; $i++) {
            $path = $dir . '/wp-config.php';
            if (file_exists($path)) {
                return $path;
            }
            $path = $dir . '/config/application.php'; // Bedrock config
            if (file_exists($path)) {
                return $path;
            }
            $dir = dirname($dir);
            // Stop traversing if we hit root early
            if ($dir === '/' || $dir === '.' || empty($dir)) {
                break;
            }
        }
    }
    return false;
}

// Helper function to find .env file for Bedrock setups
function find_env_file() {
    $search_dirs = [dirname(__FILE__)];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $search_dirs[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    }

    foreach ($search_dirs as $dir) {
        for ($i = 0; $i < 6; $i++) {
            $path = $dir . '/.env';
            if (file_exists($path)) {
                return $path;
            }
            $dir = dirname($dir);
            // Stop traversing if we hit root early
            if ($dir === '/' || $dir === '.' || empty($dir)) {
                break;
            }
        }
    }
    return false;
}

// Helper function to get database connection
function get_db_connection() {
    $db_name = getenv('DB_NAME') ?: (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '');
    $db_user = getenv('DB_USER') ?: (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : '');
    $db_pass = getenv('DB_PASSWORD') ?: (isset($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : '');
    $db_host = getenv('DB_HOST') ?: (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : '');

    if (empty($db_name) || empty($db_user)) {
        $env_path = find_env_file();
        if ($env_path) {
            $env_content = file_get_contents($env_path);
            preg_match("/^DB_NAME\s*=\s*['\"]?([^'\"\r\n]+)/m", $env_content, $db_name_matches);
            preg_match("/^DB_USER\s*=\s*['\"]?([^'\"\r\n]+)/m", $env_content, $db_user_matches);
            preg_match("/^DB_PASSWORD\s*=\s*['\"]?([^'\"\r\n]*)/m", $env_content, $db_pass_matches);
            preg_match("/^DB_HOST\s*=\s*['\"]?([^'\"\r\n]+)/m", $env_content, $db_host_matches);

            if (!empty($db_name_matches)) $db_name = $db_name_matches[1];
            if (!empty($db_user_matches)) $db_user = $db_user_matches[1];
            if (!empty($db_pass_matches)) $db_pass = $db_pass_matches[1];
            if (!empty($db_host_matches)) $db_host = $db_host_matches[1];
        }
    }

    if (empty($db_name) || empty($db_user)) {
        $wp_config_path = get_wp_config_path();
        if ($wp_config_path) {
            $config_content = file_get_contents($wp_config_path);
            preg_match("/define\(\s*'DB_NAME',\s*'([^']+)'\s*\);/", $config_content, $db_name_matches);
            preg_match("/define\(\s*'DB_USER',\s*'([^']+)'\s*\);/", $config_content, $db_user_matches);
            preg_match("/define\(\s*'DB_PASSWORD',\s*'([^']*)'\s*\);/", $config_content, $db_pass_matches);
            preg_match("/define\(\s*'DB_HOST',\s*'([^']+)'\s*\);/", $config_content, $db_host_matches);

            if (!empty($db_name_matches)) $db_name = $db_name_matches[1];
            if (!empty($db_user_matches)) $db_user = $db_user_matches[1];
            if (!empty($db_pass_matches)) $db_pass = $db_pass_matches[1];
            if (!empty($db_host_matches)) $db_host = $db_host_matches[1];
        }
    }

    if (empty($db_name) || empty($db_user)) {
        return ['error' => 'Could not parse database credentials from wp-config.php or .env.'];
    }

    if (empty($db_host)) {
        $db_host = 'localhost';
    }

    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($mysqli->connect_error) {
        return ['error' => 'Database Connection Failed. Update credentials in wp-config.php or .env.'];
    }

    $mysqli->query("SET FOREIGN_KEY_CHECKS=0;");
    $mysqli->query("SET SESSION sql_mode = '';");
    $mysqli->set_charset("utf8mb4");

    return $mysqli;
}

// ---------------------------------------------------------
// UPDATE DB CREDENTIALS AJAX Handler
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'update_db_creds') {
    header('Content-Type: application/json');
    $db_name = isset($_POST['db_name']) ? $_POST['db_name'] : '';
    $db_user = isset($_POST['db_user']) ? $_POST['db_user'] : '';
    $db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
    $db_host = isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost';

    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        echo json_encode(['error' => 'Connection Failed: ' . $mysqli->connect_error]);
        exit;
    }
    $mysqli->close();

    $wp_config_path = get_wp_config_path();
    $env_path = find_env_file();

    if ($env_path) {
        $config = file_get_contents($env_path);
        $config = preg_replace("/^DB_NAME\s*=\s*['\"]?[^'\"\r\n]+/m", "DB_NAME='" . str_replace("'", "\'", $db_name) . "'", $config);
        $config = preg_replace("/^DB_USER\s*=\s*['\"]?[^'\"\r\n]+/m", "DB_USER='" . str_replace("'", "\'", $db_user) . "'", $config);
        $config = preg_replace("/^DB_PASSWORD\s*=\s*['\"]?[^'\"\r\n]*/m", "DB_PASSWORD='" . str_replace("'", "\'", $db_pass) . "'", $config);
        $config = preg_replace("/^DB_HOST\s*=\s*['\"]?[^'\"\r\n]+/m", "DB_HOST='" . str_replace("'", "\'", $db_host) . "'", $config);

        if (file_put_contents($env_path, $config) === false) {
            echo json_encode(['error' => 'Failed to write to .env. Check file permissions.']);
            exit;
        }
    } elseif ($wp_config_path) {
        $config = file_get_contents($wp_config_path);
        $config = preg_replace("/define\(\s*'DB_NAME',\s*'[^']*'\s*\);/", "define( 'DB_NAME', '" . str_replace("'", "\'", $db_name) . "' );", $config);
        $config = preg_replace("/define\(\s*'DB_USER',\s*'[^']*'\s*\);/", "define( 'DB_USER', '" . str_replace("'", "\'", $db_user) . "' );", $config);
        $config = preg_replace("/define\(\s*'DB_PASSWORD',\s*'[^']*'\s*\);/", "define( 'DB_PASSWORD', '" . str_replace("'", "\'", $db_pass) . "' );", $config);
        $config = preg_replace("/define\(\s*'DB_HOST',\s*'[^']*'\s*\);/", "define( 'DB_HOST', '" . str_replace("'", "\'", $db_host) . "' );", $config);

        if (file_put_contents($wp_config_path, $config) === false) {
            echo json_encode(['error' => 'Failed to write to wp-config.php. Check file permissions.']);
            exit;
        }
    } else {
        echo json_encode(['error' => 'Neither wp-config.php nor .env found to update.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ---------------------------------------------------------
// PHASE 0: Zip Extraction AJAX Handler
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'extract_zip') {
    header('Content-Type: application/json');
    $zip_file = isset($_POST['zip_file']) ? $_POST['zip_file'] : '';
    $file_index = isset($_GET['file_index']) ? intval($_GET['file_index']) : 0;
    
    if (empty($zip_file) || !file_exists(dirname(__FILE__) . '/' . $zip_file)) {
        echo json_encode(['error' => 'Zip file not found.']);
        exit;
    }
    
    if (!class_exists('ZipArchive')) {
        echo json_encode(['error' => 'ZipArchive PHP extension is missing on this server. Cannot extract.']);
        exit;
    }

    $zip_path = dirname(__FILE__) . '/' . $zip_file;
    $zip = new ZipArchive;
    if ($zip->open($zip_path) !== TRUE) {
        echo json_encode(['error' => 'Failed to open zip archive.']);
        exit;
    }
    
    $num_files = $zip->numFiles;
    if ($file_index >= $num_files) {
        $zip->close();
        echo json_encode(['complete' => true, 'progress' => 100]);
        exit;
    }

    if ($file_index === 0) {
        $zip_size = filesize($zip_path);
        $free_space = disk_free_space(dirname(__FILE__));
        if ($free_space !== false && $free_space < ($zip_size * 2.5)) {
            $zip->close();
            echo json_encode(['error' => 'Insufficient disk space. Extraction requires approx ' . round(($zip_size * 2.5) / 1048576, 2) . ' MB free space.']);
            exit;
        }
        
        $extract_path_test = dirname(__FILE__);
        if (!is_writable($extract_path_test)) {
            $zip->close();
            echo json_encode(['error' => 'Permission Denied: The destination directory (' . $extract_path_test . ') is not writable by the web server user. Fix file permissions to restore.']);
            exit;
        }
    }

    $start_time = microtime(true);
    $max_execution_time = 3.0; // 3 seconds per chunk
    $batch_size = 500; // max files per chunk
    $extracted_count = 0;
    
    $extract_path = dirname(__FILE__);
    
    // Detect custom WP_CONTENT_DIR from wp-config.php to handle environments correctly
    $custom_wp_content = false;
    $wp_config_path = get_wp_config_path();
    if ($wp_config_path) {
        $config_content = @file_get_contents($wp_config_path);
        if ($config_content && preg_match("/define\(\s*['\"]WP_CONTENT_DIR['\"]\s*,\s*(.*?)\s*\);/i", $config_content, $matches)) {
            $def = trim($matches[1]);
            $def = str_ireplace(array('dirname(__FILE__)', '__DIR__'), "'" . dirname($wp_config_path) . "'", $def);
            $def = preg_replace('/[\'"]\s*\.\s*[\'"]/', '', $def);
            $def = trim($def, "'\" ");
            if (is_dir(dirname($def))) {
                $custom_wp_content = rtrim($def, '/\\');
            }
        }
    }
    
    while ($file_index < $num_files) {
        $filename = $zip->getNameIndex($file_index);
        
        // Batch 6: Ignore Mac specific hidden folders that cause WAF false positives and path errors
        if (strpos($filename, '__MACOSX/') !== false || strpos($filename, '.DS_Store') !== false) {
            $file_index++;
            continue;
        }
        
        // Security: Prevent path traversal within the ZIP structure
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            $file_index++;
            continue;
        }
        
        // Security: Guard core files regardless of their relative path in the ZIP
        $basename = trim(strtolower(basename((string)$filename)), " .");
        $protected_core = array('wp-config.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php');
        
        // Skip extracting the restore script itself or core configs to prevent locks/overwrites
        if ($filename !== 'emergency-restore.php' && !in_array($basename, $protected_core, true) && $filename !== false) {
            
            // Handle Custom WP_CONTENT_DIR Extraction
            if ($custom_wp_content && strpos($filename, 'wp-content/') === 0) {
                $relative_path = substr($filename, 11);
                $extracted_file_path = $custom_wp_content . '/' . ltrim($relative_path, '/\\');
                $dest_dir = dirname($extracted_file_path);
                if (!file_exists($dest_dir)) {
                    @mkdir($dest_dir, 0755, true);
                }
                if (substr($filename, -1) !== '/') {
                    $content = $zip->getFromName($filename);
                    if ($content !== false) {
                        file_put_contents($extracted_file_path, $content);
                    }
                }
            } else {
                $zip->extractTo($extract_path, array($filename));
                $extracted_file_path = $extract_path . '/' . $filename;
            }
            
            // Fix ownership/permissions 500 errors (www-data trap)
            if (file_exists($extracted_file_path)) {
                if (is_dir($extracted_file_path)) {
                    @chmod($extracted_file_path, 0755);
                } else {
                    @chmod($extracted_file_path, 0644);
                    
                    // Rename server config files to prevent immediate 500 errors or redirects that break the AJAX loop
                    $basename = basename($filename);
                    if ($basename === '.htaccess' || $basename === '.user.ini') {
                        @rename($extracted_file_path, $extracted_file_path . '.failsafe_temp');
                    }
                    
                    // Handle Drop-in Caches (Prevent Cache Poisoning)
                    if ($filename === 'wp-content/object-cache.php' || $filename === 'wp-content/advanced-cache.php') {
                        @rename($extracted_file_path, $extracted_file_path . '.failsafe_temp');
                    }
                    
                    // Handle Security of Failsafe Artifacts
                    if (in_array($filename, ['database-backup.sql', 'failsafe-manifest.json', 'failsafe-meta.json'])) {
                        $hash = substr(md5(dirname(__FILE__)), 0, 8);
                        @rename($extracted_file_path, $extract_path . '/' . $filename . '-' . $hash);
                    }
                }
            }
        }
        
        $file_index++;
        $extracted_count++;
        
        if (microtime(true) - $start_time > $max_execution_time || $extracted_count >= $batch_size) {
            break;
        }
    }
    
    $zip->close();
    
    $progress = min(100, round(($file_index / $num_files) * 100, 2));
    
    echo json_encode([
        'file_index' => $file_index,
        'progress' => $progress,
        'complete' => ($file_index >= $num_files)
    ]);
    exit;
}

// ---------------------------------------------------------
// PHASE 1: SQL Import AJAX Handler
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    header('Content-Type: application/json');
    
    $mysqli = get_db_connection();
    if (is_array($mysqli) && isset($mysqli['error'])) {
        echo json_encode($mysqli);
        exit;
    }

    $hash = substr(md5(dirname(__FILE__)), 0, 8);
    $sql_file = dirname(__FILE__) . '/database-backup.sql-' . $hash;
    if (!file_exists($sql_file)) {
        $sql_file = dirname(__FILE__) . '/database-backup.sql'; // Fallback
        if (!file_exists($sql_file)) {
            echo json_encode(['error' => 'database-backup.sql not found!']);
            exit;
        }
    }

    $file_size = filesize($sql_file);
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    if ($offset >= $file_size) {
        echo json_encode(['complete' => true, 'progress' => 100]);
        exit;
    }

    $file = fopen($sql_file, 'r');
    if (!$file) {
        echo json_encode(['error' => 'Could not read SQL file.']);
        exit;
    }

    fseek($file, $offset);
    $query = '';
    $last_safe_offset = $offset;
    
    $start_time = microtime(true);
    $max_execution_time = 3.0; // Run for 3 seconds per chunk
    
    while (($line = fgets($file)) !== false) {
        // Skip comments and empty lines if we aren't mid-query
        if (trim($query) === '' && (substr($line, 0, 2) == '--' || trim($line) == '')) {
            $last_safe_offset = ftell($file); // safe to resume after a comment
            continue;
        }

        $query .= $line;

        // Execute if line ends query
        if (substr(trim($line), -1, 1) == ';') {
            
            // Fix 1: Strip DEFINER from Views and Triggers to prevent Access Denied (1227) errors
            $query = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/', '', $query);
            $query = preg_replace('/DEFINER\s*=\s*\'[^\']+\'@\'[^\']+\'/', '', $query);
            $query = preg_replace('/DEFINER\s*=\s*[^\s]+\s/', '', $query);

            // Proactive fallback for modern MySQL 8 collations that crash older servers
            $query = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $query);
            $query = str_replace('utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', $query);

            if (!$mysqli->query($query)) {
                // Handle Max Allowed Packet / MySQL Server Gone Away
                if ($mysqli->errno === 2006 || $mysqli->errno === 1153) {
                    $is_packet_error = ($mysqli->errno === 1153);
                    @$mysqli->close();
                    $mysqli = get_db_connection();
                    if (is_array($mysqli) && isset($mysqli['error'])) {
                        echo json_encode($mysqli);
                        exit;
                    }
                    if ($is_packet_error) {
                        @$mysqli->query("SET GLOBAL max_allowed_packet=1073741824");
                        if (!$mysqli->query($query)) {
                            @file_put_contents(dirname(__FILE__) . '/failsafe-import-errors.log', "Error 1153 Max Packet Size on query: " . substr($query, 0, 200) . "...\n", FILE_APPEND);
                        }
                    }
                } 
                // If query fails due to character set/collation errors:
                // Error 1273: Unknown collation
                // Error 1115: Unknown character set
                // Error 1253: COLLATION '...' is not valid for CHARACTER SET '...'
                elseif (in_array($mysqli->errno, [1273, 1115, 1253])) {
                    // Ultimate fallback: downgrade utf8mb4 entirely to utf8 for extremely old hosts
                    $query = str_replace('utf8mb4_unicode_ci', 'utf8_general_ci', $query);
                    $query = str_replace('utf8mb4', 'utf8', $query);
                    $mysqli->query($query);
                }
            }
            
            $query = '';
            $last_safe_offset = ftell($file); // Safely mark this exact byte offset as completed
        }
        
        // Break if we've run long enough
        if (microtime(true) - $start_time > $max_execution_time) {
            break;
        }
    }
    
    // Always resume from the last safe offset. Discard any partial query, it will be re-read.
    $new_offset = $last_safe_offset;
    fclose($file);
    
    $is_complete = ($new_offset >= $file_size || feof($file));

    // Fix Table Prefix Mismatch when import completes
    if ($is_complete) {
        $imported_prefix = '';
        
        // Try deterministic prefix from failsafe-meta.json
        $meta_file_hash = dirname(__FILE__) . '/failsafe-meta.json-' . $hash;
        $meta_file = file_exists($meta_file_hash) ? $meta_file_hash : dirname(__FILE__) . '/failsafe-meta.json';
        
        if (file_exists($meta_file)) {
            $meta = json_decode(file_get_contents($meta_file), true);
            if (is_array($meta) && isset($meta['table_prefix'])) {
                $imported_prefix = $meta['table_prefix'];
            }
        }
        
        // Fallback to heuristic
        if (empty($imported_prefix)) {
            $result = $mysqli->query("SHOW TABLES LIKE '%usermeta'");
            if ($result && $row = $result->fetch_row()) {
                if (preg_match('/^(.*?)usermeta$/', $row[0], $matches)) {
                    $imported_prefix = $matches[1];
                }
            }
        }
        
        if (!empty($imported_prefix)) {
            $wp_config_path = get_wp_config_path();
            if (file_exists($wp_config_path)) {
                $config = file_get_contents($wp_config_path);
                $config_changed = false;
                
                if (preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]*)[\'"];/', $config, $prefix_matches)) {
                    if ($prefix_matches[1] !== $imported_prefix) {
                        $config = preg_replace('/\$table_prefix\s*=\s*[\'"][^\'"]*[\'"];/', '$table_prefix = \'' . str_replace("'", "\'", $imported_prefix) . '\';', $config);
                        $config_changed = true;
                    }
                }
                
                // Add FS_METHOD to prevent FTP prompt Trap
                if (strpos($config, "define('FS_METHOD'") === false && strpos($config, 'define( "FS_METHOD"') === false && strpos($config, 'define("FS_METHOD"') === false) {
                    $fs_method_code = "\n// Bypass FTP credentials trap on restored sites\ndefine('FS_METHOD', 'direct');\n";
                    if (strpos($config, "/* That's all, stop editing") !== false) {
                        $config = str_replace("/* That's all, stop editing", $fs_method_code . "/* That's all, stop editing", $config);
                    } else {
                        $config .= $fs_method_code;
                    }
                    $config_changed = true;
                }
                
                if ($config_changed) {
                    file_put_contents($wp_config_path, $config);
                }
            }
        }
    }

    $mysqli->close();

    $progress = min(100, round(($new_offset / $file_size) * 100, 2));
    
    echo json_encode([
        'offset' => $new_offset,
        'progress' => $progress,
        'complete' => $is_complete
    ]);
    exit;
}

// ---------------------------------------------------------
// PHASE 2: Search and Replace AJAX Handler
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'search_replace') {
    header('Content-Type: application/json');
    
    $old_url = isset($_POST['old_url']) ? rtrim($_POST['old_url'], '/') : '';
    $new_url = isset($_POST['new_url']) ? rtrim($_POST['new_url'], '/') : '';
    $old_abspath = isset($_POST['old_abspath']) ? rtrim($_POST['old_abspath'], '/\\') : '';
    $new_abspath = isset($_POST['new_abspath']) ? rtrim($_POST['new_abspath'], '/\\') : '';
    
    if (empty($old_url) || empty($new_url)) {
        echo json_encode(['error' => 'Missing URLs for search and replace.']);
        exit;
    }

    // Pre-calculate JSON-escaped versions for page builder data
    $old_url_escaped = str_replace('/', '\\/', $old_url);
    $new_url_escaped = str_replace('/', '\\/', $new_url);

    $mysqli = get_db_connection();
    if (is_array($mysqli) && isset($mysqli['error'])) {
        echo json_encode($mysqli);
        exit;
    }

    $table_index = isset($_GET['table_index']) ? intval($_GET['table_index']) : 0;
    $row_offset = isset($_GET['row_offset']) ? intval($_GET['row_offset']) : 0;

    // Multisite Fix on First Chunk
    if ($table_index === 0 && $row_offset === 0) {
        $wp_config_path = get_wp_config_path();
        if (file_exists($wp_config_path)) {
            $config = file_get_contents($wp_config_path);
            $config_changed = false;

            // Update hardcoded WP_HOME and WP_SITEURL if they exist
            if (preg_match("/define\(\s*['\"]WP_HOME['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", $config)) {
                $config = preg_replace("/define\(\s*['\"]WP_HOME['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", "define('WP_HOME', '$new_url');", $config);
                $config_changed = true;
            }
            if (preg_match("/define\(\s*['\"]WP_SITEURL['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", $config)) {
                $config = preg_replace("/define\(\s*['\"]WP_SITEURL['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", "define('WP_SITEURL', '$new_url');", $config);
                $config_changed = true;
            }
            
            // Fix 2: Disable FORCE_SSL_ADMIN and WP_CACHE to prevent lockouts
            if (preg_match("/define\(\s*['\"]FORCE_SSL_ADMIN['\"]\s*,\s*true\s*\);/i", $config)) {
                $config = preg_replace("/define\(\s*['\"]FORCE_SSL_ADMIN['\"]\s*,\s*true\s*\);/i", "define('FORCE_SSL_ADMIN', false);", $config);
                $config_changed = true;
            }
            if (preg_match("/define\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\);/i", $config)) {
                $config = preg_replace("/define\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\);/i", "define('WP_CACHE', false);", $config);
                $config_changed = true;
            }

            // Fix 5: Force DB_COLLATE to empty to prevent MySQL 8/5.7 connection mismatch errors
            if (preg_match("/define\(\s*['\"]DB_COLLATE['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/i", $config)) {
                $config = preg_replace("/define\(\s*['\"]DB_COLLATE['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/i", "define('DB_COLLATE', '');", $config);
                $config_changed = true;
            }
            
            // Batch 4: Disable DISALLOW_FILE_MODS and DISALLOW_FILE_EDIT to prevent admin lockout
            if (preg_match("/define\(\s*['\"]DISALLOW_FILE_MODS['\"]\s*,\s*true\s*\);/i", $config)) {
                $config = preg_replace("/define\(\s*['\"]DISALLOW_FILE_MODS['\"]\s*,\s*true\s*\);/i", "define('DISALLOW_FILE_MODS', false);", $config);
                $config_changed = true;
            }
            if (preg_match("/define\(\s*['\"]DISALLOW_FILE_EDIT['\"]\s*,\s*true\s*\);/i", $config)) {
                $config = preg_replace("/define\(\s*['\"]DISALLOW_FILE_EDIT['\"]\s*,\s*true\s*\);/i", "define('DISALLOW_FILE_EDIT', false);", $config);
                $config_changed = true;
            }
            
            // Batch 5: Rewrite Absolute Paths and Cookies, Disable Cache Servers
            $constants_to_empty = ['WP_REDIS_HOST', 'WP_REDIS_PORT', 'WP_CACHE_KEY_SALT', 'MEMCACHED_SERVERS', 'COOKIE_DOMAIN', 'COOKIEPATH', 'SITECOOKIEPATH'];
            foreach ($constants_to_empty as $const) {
                if (preg_match("/define\(\s*['\"]" . $const . "['\"]\s*,/i", $config)) {
                    $config = preg_replace("/define\(\s*['\"]" . $const . "['\"]\s*,\s*[^)]+\s*\);/i", "define('$const', '');", $config);
                    $config_changed = true;
                }
            }
            
            $constants_to_dir = ['WP_CONTENT_DIR', 'WP_PLUGIN_DIR'];
            foreach ($constants_to_dir as $const) {
                if (preg_match("/define\(\s*['\"]" . $const . "['\"]\s*,/i", $config)) {
                    $suffix = ($const === 'WP_PLUGIN_DIR') ? '/wp-content/plugins' : '/wp-content';
                    $config = preg_replace("/define\(\s*['\"]" . $const . "['\"]\s*,\s*[^)]+\s*\);/i", "define('$const', __DIR__ . '$suffix');", $config);
                    $config_changed = true;
                }
            }
            
            // Fix 4: Disable WP-Cron (Safe Boot)
            $disable_cron = isset($_POST['disable_cron']) && $_POST['disable_cron'] === '1';
            if ($disable_cron) {
                if (preg_match("/define\(\s*['\"]DISABLE_WP_CRON['\"]\s*,\s*(true|false)\s*\);/i", $config)) {
                    $config = preg_replace("/define\(\s*['\"]DISABLE_WP_CRON['\"]\s*,\s*(true|false)\s*\);/i", "define('DISABLE_WP_CRON', true);", $config);
                } else {
                    $config = str_replace("/* That's all, stop editing!", "define('DISABLE_WP_CRON', true);\n/* That's all, stop editing!", $config);
                }
                $config_changed = true;
            }
            
            // Batch 7: Force session.save_path to /tmp to prevent fatal lockouts on hosts like Pantheon/cPanel
            if (strpos($config, 'session.save_path') === false) {
                $config = str_replace("/* That's all, stop editing!", "@ini_set('session.save_path', '/tmp');\n/* That's all, stop editing!", $config);
                $config_changed = true;
            }

            if (strpos($config, 'MULTISITE') !== false || strpos($config, 'SUBDOMAIN_INSTALL') !== false) {
                // Extract bare domains (e.g. from https://oldsite.com to oldsite.com)
                $old_host = parse_url((preg_match('#^https?://#i', $old_url) ? '' : 'http://') . $old_url, PHP_URL_HOST) ?: $old_url;
                $new_host = parse_url((preg_match('#^https?://#i', $new_url) ? '' : 'http://') . $new_url, PHP_URL_HOST) ?: $new_url;
                
                // Update DOMAIN_CURRENT_SITE in wp-config.php
                if (preg_match("/define\(\s*['\"]DOMAIN_CURRENT_SITE['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", $config)) {
                    $config = preg_replace("/define\(\s*['\"]DOMAIN_CURRENT_SITE['\"]\s*,\s*['\"][^'\"]*['\"]\s*\);/", "define('DOMAIN_CURRENT_SITE', '$new_host');", $config);
                    $config_changed = true;
                }

                // Update wp_blogs and wp_site domains (standard S&R misses these because they are stored as bare domains)
                $tables_to_check = [];
                $res_blogs = $mysqli->query("SHOW TABLES LIKE '%_blogs'");
                if ($res_blogs) {
                    while ($row = $res_blogs->fetch_row()) $tables_to_check[] = $row[0];
                }
                $res_site = $mysqli->query("SHOW TABLES LIKE '%_site'");
                if ($res_site) {
                    while ($row = $res_site->fetch_row()) $tables_to_check[] = $row[0];
                }

                foreach ($tables_to_check as $ms_table) {
                    $stmt = $mysqli->prepare("UPDATE `$ms_table` SET `domain` = ? WHERE `domain` = ?");
                    if ($stmt) {
                        $stmt->bind_param('ss', $new_host, $old_host);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            // Batch 5: Strip trailing whitespace and PHP closing tag to prevent "Headers already sent" fatal errors
            $trimmed_config = preg_replace('/\?' . '>\s*$/', '', trim($config));
            if ($trimmed_config !== $config) {
                $config = $trimmed_config;
                $config_changed = true;
            }
            
            if ($config_changed) {
                file_put_contents($wp_config_path, $config);
            }
        }
    }

    // Get all tables
    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    if ($table_index >= count($tables)) {
        echo json_encode(['complete' => true, 'progress' => 100]);
        exit;
    }

    $current_table = $tables[$table_index];
    
    // Batch 7: Truncate notoriously bloated log tables to prevent timeouts on massive e-commerce sites
    if (preg_match('/_actionscheduler_logs$/', $current_table) || preg_match('/_woocommerce_sessions$/', $current_table)) {
        if ($row_offset === 0) {
            $mysqli->query("TRUNCATE TABLE `$current_table`");
        }
        // Skip search-replace processing for these truncated tables entirely to save time
        echo json_encode(['table' => $current_table, 'progress' => 100, 'next_offset' => 0, 'next_table' => $table_index + 1]);
        exit;
    }
    
    // Fix 3: Purge transient caches from wp_options before processing to save memory/time and prevent corruption
    if (preg_match('/_options$/', $current_table) && $row_offset === 0) {
        $mysqli->query("DELETE FROM `$current_table` WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'");
        
        // Fix 2: SEO Duplicate Content Penalty - discourage search engines on staging
        if (isset($_POST['disable_seo']) && $_POST['disable_seo'] === '1') {
            $mysqli->query("UPDATE `$current_table` SET option_value = '0' WHERE option_name = 'blog_public'");
        }
        
        // Batch 4: Empty upload_path and upload_url_path to prevent absolute path breakage on new server
        $mysqli->query("UPDATE `$current_table` SET option_value = '' WHERE option_name IN ('upload_path', 'upload_url_path')");
        
        // Batch 5: Wipe security plugin IP lockouts from database
        $mysqli->query("DELETE FROM `$current_table` WHERE option_name IN ('limit_login_lockouts', 'limit_login_retries', 'itsec_lockouts', 'jetpack_protect_blocked_ips') OR option_name LIKE 'wordfence\_ls\_%'");
        
        // Batch 6: Force deactivate known staging crashers (WP Rocket, Wordfence, W3TC, Really Simple SSL, iThemes)
        $res = $mysqli->query("SELECT option_value FROM `$current_table` WHERE option_name = 'active_plugins'");
        if ($res && $row = $res->fetch_row()) {
            $plugins = @unserialize($row[0]);
            if (is_array($plugins)) {
                $crashers = [
                    'wp-rocket/wp-rocket.php',
                    'wordfence/wordfence.php',
                    'w3-total-cache/w3-total-cache.php',
                    'really-simple-ssl/rlrsssl-really-simple-ssl.php',
                    'ithemes-security-pro/ithemes-security-pro.php',
                    'better-wp-security/better-wp-security.php'
                ];
                $plugins = array_diff($plugins, $crashers);
                $new_val = $mysqli->real_escape_string(serialize(array_values($plugins)));
                $mysqli->query("UPDATE `$current_table` SET option_value = '$new_val' WHERE option_name = 'active_plugins'");
            }
        }
    }
    
    // Batch 6: Wipe ghost session tokens to prevent instant logout loops
    if (preg_match('/_usermeta$/', $current_table) && $row_offset === 0) {
        $mysqli->query("DELETE FROM `$current_table` WHERE meta_key = 'session_tokens'");
    }
    
    // Get primary key
    $primary_key = '';
    $pk_result = $mysqli->query("SHOW KEYS FROM `$current_table` WHERE Key_name = 'PRIMARY'");
    if ($pk_result && $pk_row = $pk_result->fetch_assoc()) {
        $primary_key = $pk_row['Column_name'];
    }

    $start_time = microtime(true);
    $max_execution_time = 3.0;
    $limit = 500; // rows per chunk
    
    $query = "SELECT * FROM `$current_table` LIMIT $limit OFFSET $row_offset";
    $rows = $mysqli->query($query);
    
    $rows_processed = 0;
    $updates_made = 0;

    if ($rows && $rows->num_rows > 0) {
        function recursive_unserialize_replace($from, $to, $data, $serialised = false) {
            try {
                if (is_string($data) && ($unserialized = @unserialize($data)) !== false && $data !== 'b:0;') {
                    $data = recursive_unserialize_replace($from, $to, $unserialized, true);
                } elseif (is_array($data)) {
                    $_tmp = array();
                    foreach ($data as $key => $value) {
                        $_tmp[$key] = recursive_unserialize_replace($from, $to, $value, false);
                    }
                    $data = $_tmp;
                } elseif (is_object($data)) {
                    $_tmp = clone $data;
                    $props = get_object_vars($_tmp);
                    foreach ($props as $key => $value) {
                        $_tmp->$key = recursive_unserialize_replace($from, $to, $value, false);
                    }
                    $data = $_tmp;
                } elseif (is_string($data)) {
                    $data = str_replace($from, $to, $data);
                }
                
                if ($serialised) {
                    return serialize($data);
                }
            } catch (Exception $e) {}
            return $data;
        }

        while ($row = $rows->fetch_assoc()) {
            $needs_update = false;
            $update_queries = [];
            
            foreach ($row as $col => $val) {
                if (is_string($val)) {
                    $new_val = $val;
                    if (!empty($old_url) && !empty($new_url)) {
                        if (strpos($new_val, $old_url) !== false) {
                            $new_val = recursive_unserialize_replace($old_url, $new_url, $new_val, false);
                        }
                        if (strpos($new_val, $old_url_escaped) !== false) {
                            $new_val = str_replace($old_url_escaped, $new_url_escaped, $new_val);
                        }
                    }
                    
                    if (!empty($old_abspath) && !empty($new_abspath) && $old_abspath !== $new_abspath) {
                        if (strpos($new_val, $old_abspath) !== false) {
                            $new_val = recursive_unserialize_replace($old_abspath, $new_abspath, $new_val, false);
                        }
                        $old_abs_esc = str_replace('/', '\\/', $old_abspath);
                        if (strpos($new_val, $old_abs_esc) !== false) {
                            $new_abs_esc = str_replace('/', '\\/', $new_abspath);
                            $new_val = str_replace($old_abs_esc, $new_abs_esc, $new_val);
                        }
                        $old_abs_esc_win = str_replace('\\', '\\\\', $old_abspath);
                        if (strpos($new_val, $old_abs_esc_win) !== false) {
                            $new_abs_esc_win = str_replace('\\', '\\\\', $new_abspath);
                            $new_val = str_replace($old_abs_esc_win, $new_abs_esc_win, $new_val);
                        }
                    }

                    if ($new_val !== $val) {
                        $needs_update = true;
                        $update_queries[] = "`$col` = '" . $mysqli->real_escape_string($new_val) . "'";
                    }
                }
            }

            if ($needs_update) {
                if ($primary_key) {
                    $pk_val = $mysqli->real_escape_string($row[$primary_key]);
                    $update_sql = "UPDATE `$current_table` SET " . implode(', ', $update_queries) . " WHERE `$primary_key` = '$pk_val'";
                    $mysqli->query($update_sql);
                    $updates_made++;
                } else {
                    // Table without primary key - fallback to matching all columns (slower/risky, but rare in WP)
                    $where = [];
                    foreach ($row as $col => $val) {
                        if ($val === null) {
                            $where[] = "`$col` IS NULL";
                        } else {
                            $where[] = "`$col` = '" . $mysqli->real_escape_string($val) . "'";
                        }
                    }
                    $update_sql = "UPDATE `$current_table` SET " . implode(', ', $update_queries) . " WHERE " . implode(' AND ', $where) . " LIMIT 1";
                    $mysqli->query($update_sql);
                    $updates_made++;
                }
            }
            $rows_processed++;
            
            if (microtime(true) - $start_time > $max_execution_time) {
                break;
            }
        }
    }
    
    $total_tables = count($tables);
    $base_progress = ($table_index / $total_tables) * 100;
    
    if ($rows && $rows_processed == $limit) {
        // More rows in this table
        $new_row_offset = $row_offset + $rows_processed;
        $progress = $base_progress; 
        echo json_encode([
            'table_index' => $table_index,
            'row_offset' => $new_row_offset,
            'progress' => round($progress, 2),
            'log' => "Processed $rows_processed rows in $current_table..."
        ]);
    } else {
        // Table finished, move to next
        $new_table_index = $table_index + 1;
        $progress = ($new_table_index / $total_tables) * 100;
        echo json_encode([
            'table_index' => $new_table_index,
            'row_offset' => 0,
            'progress' => min(100, round($progress, 2)),
            'log' => "Finished table $current_table. Moving to next..."
        ]);
    }
    
    $mysqli->close();
    exit;
}

// ---------------------------------------------------------
// PHASE 3: Malware Purging AJAX Handlers
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'scan_rogue') {
    header('Content-Type: application/json');

    $hash = substr(md5(dirname(__FILE__)), 0, 8);
    $manifest_file = dirname(__FILE__) . '/failsafe-manifest.json-' . $hash;
    if (!file_exists($manifest_file)) $manifest_file = dirname(__FILE__) . '/failsafe-manifest.json';
    
    if (!file_exists($manifest_file)) {
        echo json_encode(['complete' => true, 'rogue_files' => []]);
        exit;
    }

    function wp_normalize_path_lite($path) {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        return ltrim($path, '/');
    }

    $state_file = dirname(__FILE__) . '/.failsafe_scan_state';
    
    if (isset($_GET['reset']) && $_GET['reset'] === '1') {
        @unlink($state_file);
    }

    $state = [];
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
    }

    $rootPath = dirname(__FILE__);

    if (empty($state)) {
        $state = [
            'dir_queue' => [$rootPath],
            'rogue_files' => [],
            'files_scanned' => 0
        ];
    }

    // Free memory immediately by decoding directly to a flipped map
    $manifest_json = file_get_contents($manifest_file);
    $manifest = json_decode($manifest_json, true);
    if (!is_array($manifest)) {
        echo json_encode(['error' => 'Invalid manifest file.']);
        exit;
    }
    
    $manifest_map = array_flip(array_map('wp_normalize_path_lite', $manifest));
    unset($manifest); // Free memory
    unset($manifest_json);

    $start_time = microtime(true);
    $time_limit = 3.0;
    
    while (!empty($state['dir_queue'])) {
        $current_dir = array_shift($state['dir_queue']);
        
        if (!is_dir($current_dir) || !is_readable($current_dir)) {
            continue;
        }

        $items = @scandir($current_dir);
        if ($items === false) continue;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = rtrim($current_dir, '/\\') . '/' . $item;
            
            if (is_dir($path) && !is_link($path)) {
                $state['dir_queue'][] = $path;
            } else {
                $relativePath = wp_normalize_path_lite(substr($path, strlen($rootPath)));
                
                // Exclude failsafe artifacts and user uploads (data loss prevention)
                $is_artifact = strpos($relativePath, 'database-backup.sql') === 0 || 
                               strpos($relativePath, 'failsafe-manifest.json') === 0 || 
                               strpos($relativePath, 'failsafe-meta.json') === 0 || 
                               strpos($relativePath, 'wp-content/uploads/') === 0 ||
                               $relativePath === 'emergency-restore.php' ||
                               $relativePath === '.failsafe_scan_state' ||
                               strpos($relativePath, '.failsafe_temp') !== false;
                
                if (!$is_artifact && !isset($manifest_map[$relativePath])) {
                    $state['rogue_files'][] = $relativePath;
                }
                
                $state['files_scanned']++;
            }
        }
        
        if (microtime(true) - $start_time > $time_limit) {
            file_put_contents($state_file, json_encode($state));
            echo json_encode([
                'complete' => false,
                'scanned' => $state['files_scanned'],
                'rogues_found' => count($state['rogue_files'])
            ]);
            exit;
        }
    }
    
    $final_rogues = $state['rogue_files'];
    @unlink($state_file);
    
    echo json_encode([
        'complete' => true,
        'rogue_files' => $final_rogues,
        'scanned' => $state['files_scanned']
    ]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'purge_rogue') {
    header('Content-Type: application/json');
    $files_to_delete = isset($_POST['files']) ? json_decode($_POST['files'], true) : [];
    
    if (!is_array($files_to_delete)) {
        echo json_encode(['error' => 'Invalid file list provided.']);
        exit;
    }

    $rootPath = dirname(__FILE__);
    $deleted_count = 0;
    $errors = [];

    $protected_core_files = [
        'wp-config.php', 'wp-settings.php', 'wp-load.php', 'index.php',
        '.htaccess', 'wp-blog-header.php', 'wp-cron.php', 'wp-links-opml.php',
        'wp-login.php', 'wp-mail.php', 'wp-signup.php', 'wp-trackback.php',
        'xmlrpc.php'
    ];

    foreach ($files_to_delete as $relativePath) {
        // Simple directory traversal protection
        if (strpos($relativePath, '..') !== false) {
            continue;
        }

        // Better path normalization to resolve `./` and duplicate slashes
        $normalizedPath = str_replace('\\', '/', $relativePath);
        // Remove trailing and leading spaces
        $normalizedPath = trim($normalizedPath);
        // Resolve `./` components
        $normalizedPath = preg_replace('|/\./|', '/', $normalizedPath);
        $normalizedPath = preg_replace('|^(\./)+|', '', $normalizedPath);
        // Strip duplicate slashes
        $normalizedPath = preg_replace('|/+|', '/', $normalizedPath);
        $normalizedPath = ltrim($normalizedPath, '/');

        $baseName = trim(strtolower(basename($normalizedPath)), " .");
        $lowerNormalizedPath = strtolower($normalizedPath);
        
        // Failsafe: Never allow deletion of protected core files or directories
        if (in_array($baseName, $protected_core_files) || strpos($lowerNormalizedPath, 'wp-admin/') === 0 || strpos($lowerNormalizedPath, 'wp-includes/') === 0) {
            $errors[] = "Protected core file cannot be deleted: $relativePath";
            continue;
        }

        $filePath = rtrim($rootPath, '/\\') . '/' . ltrim($relativePath, '/\\');
        
        if (file_exists($filePath)) {
            if (@unlink($filePath)) {
                $deleted_count++;
            } else {
                $errors[] = "Failed to delete: $relativePath";
            }
        }
    }

    echo json_encode([
        'success' => true, 
        'deleted_count' => $deleted_count,
        'errors' => $errors
    ]);
    exit;
}


if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $new_url = isset($_POST['new_url']) ? rtrim($_POST['new_url'], '/') : '';
    $new_path = parse_url($new_url, PHP_URL_PATH);
    if (empty($new_path)) {
        $new_path = '/';
    } else {
        $new_path = rtrim($new_path, '/') . '/';
    }

    // Disable drop-ins and mu-plugins to prevent WSOD (Host conflicts)
    $items_to_disable = ['object-cache.php', 'advanced-cache.php', 'db.php', 'mu-plugins'];
    foreach ($items_to_disable as $item) {
        $path = dirname(__FILE__) . '/wp-content/' . $item;
        if (file_exists($path)) {
            @rename($path, $path . '.disabled');
        }
    }
    
    // Fix 1: Disable Outgoing Emails for Staging Environments
    if (isset($_POST['disable_emails']) && $_POST['disable_emails'] === '1') {
        if (!is_dir(dirname(__FILE__) . '/wp-content/mu-plugins')) {
            @mkdir(dirname(__FILE__) . '/wp-content/mu-plugins', 0755, true);
        }
        $mu_plugin = "<?php\n/*\nPlugin Name: Failsafe Disable Emails\nDescription: Blocks all outgoing emails. Added by Doomsday Failsafe for staging environments. Delete this file to enable emails.\n*/\nadd_filter('pre_wp_mail', '__return_false');";
        file_put_contents(dirname(__FILE__) . '/wp-content/mu-plugins/failsafe-disable-emails.php', $mu_plugin);
    }
    
    // Nuke Page Builder physical CSS caches so they regenerate with new URLs
    function failsafe_recursive_delete_dir($dir) {
        if (is_dir($dir)) {
            $objects = @scandir($dir);
            if ($objects !== false) {
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                            failsafe_recursive_delete_dir($dir . DIRECTORY_SEPARATOR . $object);
                        else
                            @unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            @rmdir($dir);
        }
    }
    
    $cache_dirs = [
        dirname(__FILE__) . '/wp-content/uploads/elementor/css',
        dirname(__FILE__) . '/wp-content/et-cache',
        dirname(__FILE__) . '/wp-content/cache'
    ];
    foreach ($cache_dirs as $dir) {
        failsafe_recursive_delete_dir($dir);
    }

    // Restore server config files, strip Wordfence lockouts, and fix RewriteBase
    $config_files = ['.htaccess', '.user.ini', 'php.ini', 'php5.ini'];
    foreach ($config_files as $file) {
        $temp_path = dirname(__FILE__) . '/' . $file . '.failsafe_temp';
        $real_path = dirname(__FILE__) . '/' . $file;
        if (file_exists($temp_path)) {
            @rename($temp_path, $real_path);
        }
        
        // Fix 4: Create .htaccess if it's completely missing (e.g., from Nginx)
        if ($file === '.htaccess' && !file_exists($real_path)) {
            $default_htaccess = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase $new_path\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . {$new_path}index.php [L]\n</IfModule>";
            file_put_contents($real_path, $default_htaccess);
        }
        
        if (file_exists($real_path)) {
            $content = file_get_contents($real_path);
            $changed = false;
            
            // Fix 2: Remove Wordfence / Security plugin auto_prepend_file lockouts
            if (preg_match('/^php_value\s+auto_prepend_file\s+.*$/im', $content)) {
                $content = preg_replace('/^php_value\s+auto_prepend_file\s+.*$/im', '', $content);
                $changed = true;
            }
            if (preg_match('/^auto_prepend_file\s*=\s*.*$/im', $content)) {
                $content = preg_replace('/^auto_prepend_file\s*=\s*.*$/im', '', $content);
                $changed = true;
            }
            
            // Fix 4: Fix RewriteBase for subfolders in .htaccess
            if ($file === '.htaccess') {
                if (preg_match('/^RewriteBase\s+.*$/im', $content)) {
                    $content = preg_replace('/^RewriteBase\s+.*$/im', 'RewriteBase ' . $new_path, $content);
                    $changed = true;
                }
                if (preg_match('/^RewriteRule\s+\.\s+\/index\.php\s+\[L\]$/im', $content)) {
                    $content = preg_replace('/^RewriteRule\s+\.\s+\/index\.php\s+\[L\]$/im', 'RewriteRule . ' . $new_path . 'index.php [L]', $content);
                    $changed = true;
                }
                
                // Batch 7: Comment out HTTPS forces in .htaccess if the new URL is HTTP
                if (strpos($new_url, 'http://') === 0) {
                    if (preg_match('/^(.*RewriteCond %\{HTTPS\}.*)$/mi', $content) || preg_match('/^(.*RewriteRule .* https:\/\/.*)$/mi', $content)) {
                        $content = preg_replace('/^(.*RewriteCond %\{HTTPS\}.*)$/mi', '#$1', $content);
                        $content = preg_replace('/^(.*RewriteRule .* https:\/\/.*)$/mi', '#$1', $content);
                        $content = preg_replace('/^(.*SetEnv HTTPS "on".*)$/mi', '#$1', $content);
                        $changed = true;
                    }
                }
                
                // Batch 4: Strip W3TC, WP Super Cache, Wordfence, and iThemes Security blocks from .htaccess
                $patterns_to_strip = [
                    '/#\s*BEGIN\s+W3TC.*?#\s*END\s+W3TC.*?(?:\r\n|\n)?/is',
                    '/#\s*BEGIN\s+WPSuperCache.*?#\s*END\s+WPSuperCache.*?(?:\r\n|\n)?/is',
                    '/#\s*BEGIN\s+Wordfence.*?#\s*END\s+Wordfence.*?(?:\r\n|\n)?/is',
                    '/#\s*BEGIN\s+iThemes Security.*?#\s*END\s+iThemes Security.*?(?:\r\n|\n)?/is'
                ];
                foreach ($patterns_to_strip as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '', $content);
                        $changed = true;
                    }
                }

                // Batch 11: Strip host-specific PHP handlers that cause 500 errors or force file downloads
                $patterns_to_strip_handlers = [
                    '/^\s*AddHandler\s+application\/x-httpd-(ea-)?php.*$/mi',
                    '/^\s*AddType\s+application\/x-httpd-(ea-)?php.*$/mi'
                ];
                foreach ($patterns_to_strip_handlers as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '', $content);
                        $changed = true;
                    }
                }
            }
            
            if ($changed) {
                file_put_contents($real_path, $content);
            }
        }
    }

    $hash = substr(md5(dirname(__FILE__)), 0, 8);
    @unlink(dirname(__FILE__) . '/database-backup.sql-' . $hash);
    @unlink(dirname(__FILE__) . '/failsafe-meta.json-' . $hash);
    @unlink(dirname(__FILE__) . '/failsafe-manifest.json-' . $hash);
    
    // Batch 11: Purge maintenance mode lockout
    @unlink(dirname(__FILE__) . '/.maintenance');
    
    @unlink(dirname(__FILE__) . '/database-backup.sql');
    @unlink(dirname(__FILE__) . '/failsafe-meta.json');
    @unlink(dirname(__FILE__) . '/failsafe-manifest.json');
    
    // Fix 5: Remove .maintenance trap
    @unlink(dirname(__FILE__) . '/.maintenance');
    
    // Batch 7: Rename default parking pages (e.g. index.html) that block WordPress index.php
    $parking_pages = ['index.html', 'index.htm', 'default.html'];
    foreach ($parking_pages as $page) {
        $page_path = dirname(__FILE__) . '/' . $page;
        if (file_exists($page_path) && !is_dir($page_path)) {
            @rename($page_path, $page_path . '.failsafe_disabled');
        }
    }
    
    @unlink(__FILE__);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Pre-Flight Checks
$hash = substr(md5(dirname(__FILE__)), 0, 8);
$meta_file = dirname(__FILE__) . '/failsafe-meta.json-' . $hash;
if (!file_exists($meta_file)) $meta_file = dirname(__FILE__) . '/failsafe-meta.json';
$warnings = [];
$is_nginx = stripos(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '', 'nginx') !== false;

if (file_exists($meta_file)) {
    $meta = json_decode(file_get_contents($meta_file), true);
    if ($meta) {
        $source_php = isset($meta['php_version']) ? $meta['php_version'] : '';
        $source_server = isset($meta['server_software']) ? $meta['server_software'] : '';
        $source_exts = isset($meta['extensions']) ? $meta['extensions'] : [];
        $source_url = isset($meta['old_url']) ? $meta['old_url'] : '';
        $source_abspath = isset($meta['old_abspath']) ? rtrim($meta['old_abspath'], '/\\') : '';

        // Check 1: Nginx vs Apache
        $source_is_nginx = stripos($source_server, 'nginx') !== false;
        if ($is_nginx && !$source_is_nginx) {
            $warnings[] = [
                'type' => 'nginx',
                'msg' => 'Your new server is running Nginx, but the backup was likely Apache. The included <code>.htaccess</code> file will be ignored, meaning Permalinks will break. You must add WordPress routing rules to your Nginx configuration.'
            ];
        }

        // Check 2: PHP Version
        $current_php = phpversion();
        if ($source_php && version_compare($current_php, $source_php, '<')) {
            $warnings[] = [
                'type' => 'php',
                'msg' => sprintf('Your new server is running PHP <strong>%s</strong>, but the backup was generated on PHP <strong>%s</strong>. If your plugins or theme require the newer PHP version, your site will throw fatal errors.', $current_php, $source_php)
            ];
        }

        // Check 3: Missing Extensions
        $current_exts = get_loaded_extensions();
        $required_wp_exts = ['mysqli', 'curl', 'mbstring', 'zip', 'dom', 'gd', 'imagick'];
        $missing = [];
        
        foreach ($required_wp_exts as $req_ext) {
            if (in_array($req_ext, $source_exts) && !in_array($req_ext, $current_exts)) {
                $missing[] = $req_ext;
            }
        }
        if (!empty($missing)) {
            $warnings[] = [
                'type' => 'ext',
                'msg' => 'Your new server is missing PHP extensions that were present on the old server: <strong>' . implode(', ', $missing) . '</strong>. This may break site functionality.'
            ];
        }
    }
} else {
    // If no meta file, just do basic checks
    if ($is_nginx) {
        $warnings[] = [
            'type' => 'nginx',
            'msg' => 'Your server is running Nginx. The included <code>.htaccess</code> file will be ignored, meaning Permalinks will break. You must add standard WordPress routing rules to your Nginx configuration.'
        ];
    }
}

// Test initial DB connection for UI rendering
$db_status = get_db_connection();
$db_connected = !is_array($db_status);
if ($db_connected) {
    $db_status->close();
}

// Check 4: Cache Drop-ins & MU-Plugins
$drop_ins = [];
if (file_exists(dirname(__FILE__) . '/wp-content/object-cache.php')) $drop_ins[] = 'object-cache.php';
if (file_exists(dirname(__FILE__) . '/wp-content/advanced-cache.php')) $drop_ins[] = 'advanced-cache.php';
if (is_dir(dirname(__FILE__) . '/wp-content/mu-plugins')) $drop_ins[] = 'mu-plugins directory';

if (!empty($drop_ins)) {
    $warnings[] = [
        'type' => 'cache',
        'msg' => 'We detected potential conflicts: <strong>' . implode(', ', $drop_ins) . '</strong>. If your new server does not have Redis/Memcached configured, or has incompatible host-specific mu-plugins (e.g. WP Engine/Kinsta), your site might crash after restoration. <em>Note: The restore script will automatically disable cache drop-ins and the mu-plugins folder during cleanup to ensure a safe boot. You can re-enable your custom mu-plugins via FTP later.</em>'
    ];
}

// Find available ZIP files for extraction
$available_zips = glob(dirname(__FILE__) . '/*.zip');
$zip_options = '';
foreach ($available_zips as $zip) {
    $basename = basename($zip);
    // Auto-select failsafe zip if found
    $selected = strpos($basename, 'failsafe-snapshot-') !== false ? 'selected' : '';
    $zip_options .= "<option value=\"$basename\" $selected>$basename</option>";
}
$needs_extraction = !get_wp_config_path() && !empty($available_zips);

// HTML UI
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doomsday Failsafe Recovery</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; background: #f0f2f5; color: #1d2327; max-width: 600px; margin: 50px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { margin-top: 0; color: #d63638; }
        .progress-bar-container { background: #e0e0e0; border-radius: 4px; width: 100%; height: 20px; overflow: hidden; margin-top: 20px; display: none; }
        .progress-bar { background: #2271b1; height: 100%; width: 0%; transition: width 0.3s; }
        .btn { background: #2271b1; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; display: inline-block; text-decoration: none; }
        .btn:disabled { background: #a7aaad; cursor: not-allowed; }
        #log { margin-top: 20px; font-family: monospace; font-size: 13px; color: #50575e; white-space: pre-wrap; background: #f6f7f7; padding: 10px; border: 1px solid #c3c4c7; border-radius: 4px; max-height: 200px; overflow-y: auto;}
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
        .notice { background: #fff8e5; border-left: 4px solid #f0b849; padding: 12px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚨 Doomsday Recovery</h1>
        <p>This script will carefully restore your database from <code>database-backup.sql</code> in small, safe chunks to prevent server crashes.</p>
        
        <p><strong>Note:</strong> Ensure you have updated <code>wp-config.php</code> with your current database credentials before proceeding.</p>
    </div>

    <?php if (!empty($warnings)): ?>
    <div class="card" style="border-left: 5px solid #d63638;">
        <h3 style="color: #d63638; margin-top: 0;">⚠️ Pre-Flight Warnings</h3>
        <?php foreach ($warnings as $warning): ?>
            <div class="notice" style="border-left-color: #d63638; background: #fcf0f1;">
                <?php echo $warning['msg']; ?>
                <?php if ($warning['type'] === 'nginx'): ?>
                    <pre style="background: #fff; padding: 10px; font-size: 12px; overflow-x: auto;">location / {
    try_files $uri $uri/ /index.php?$args;
}</pre>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p style="margin-bottom: 0; font-weight: bold;">You can still proceed, but you may need to fix these server issues for the site to work correctly.</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>Domain Migration (Optional)</h3>
        <div class="notice">
            If you are moving this site to a new domain name, enter the URLs below. 
            The script will intelligently update serialized database records to match the new domain after the import finishes.
        </div>
        <div class="form-group">
            <label for="oldUrl">Old Site URL</label>
            <input type="text" id="oldUrl" placeholder="https://oldsite.com" value="<?php echo htmlspecialchars(isset($source_url) ? $source_url : ''); ?>">
        </div>
        <div class="form-group">
            <label for="newUrl">New Site URL</label>
            <input type="text" id="newUrl" placeholder="https://newsite.com" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']; ?>">
        </div>
        
        <div class="form-group" style="margin-top: 15px; background: #f6f7f7; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px;">
            <p style="font-size: 13px; margin-top: 0;"><strong>Absolute Path Migration</strong> (Fixes broken page builder CSS and cache paths)</p>
            <label for="oldAbspath" style="font-size: 13px;">Old Server Path</label>
            <input type="text" id="oldAbspath" placeholder="/var/www/oldsite" value="<?php echo htmlspecialchars(isset($source_abspath) ? $source_abspath : ''); ?>" style="margin-bottom: 10px;">
            <label for="newAbspath" style="font-size: 13px;">New Server Path</label>
            <input type="text" id="newAbspath" placeholder="/var/www/newsite" value="<?php echo htmlspecialchars(rtrim(dirname(__FILE__), '/\\')); ?>">
        </div>

        <h4 style="margin-top: 20px; border-top: 1px solid #e0e0e0; padding-top: 15px;">Advanced Recovery Options (Staging Mode)</h4>
        <div class="form-group" style="display: flex; align-items: center;">
            <input type="checkbox" id="disableEmails" value="1" style="width: auto; margin-right: 10px;">
            <label for="disableEmails" style="margin-bottom: 0;">Disable Outgoing Emails <span style="font-weight: normal; font-size: 13px; color: #50575e;">(Prevents spamming customers from staging)</span></label>
        </div>
        <div class="form-group" style="display: flex; align-items: center;">
            <input type="checkbox" id="disableSeo" value="1" style="width: auto; margin-right: 10px;">
            <label for="disableSeo" style="margin-bottom: 0;">Discourage Search Engines <span style="font-weight: normal; font-size: 13px; color: #50575e;">(Prevents duplicate content penalty)</span></label>
        </div>
        <div class="form-group" style="display: flex; align-items: center;">
            <input type="checkbox" id="disableCron" value="1" style="width: auto; margin-right: 10px;">
            <label for="disableCron" style="margin-bottom: 0;">Disable WP-Cron <span style="font-weight: normal; font-size: 13px; color: #50575e;">(Prevents CPU spikes on first boot)</span></label>
        </div>
    </div>

    <?php if (!$db_connected): ?>
    <div class="card" style="border-left: 5px solid #d63638;">
        <h3 style="color: #d63638; margin-top: 0;">Database Connection Failed</h3>
        <div class="notice">
            We could not connect to the database using the credentials currently in <code>wp-config.php</code>. 
            If you moved to a new host, please provide the new database credentials below.
        </div>
        <div class="form-group">
            <label for="dbName">Database Name</label>
            <input type="text" id="dbName" placeholder="e.g. wp_database">
        </div>
        <div class="form-group">
            <label for="dbUser">Database User</label>
            <input type="text" id="dbUser" placeholder="e.g. root">
        </div>
        <div class="form-group">
            <label for="dbPass">Database Password</label>
            <input type="text" id="dbPass" placeholder="Leave empty if none">
        </div>
        <div class="form-group">
            <label for="dbHost">Database Host</label>
            <input type="text" id="dbHost" value="localhost">
        </div>
        <button id="updateCredsBtn" class="btn" style="width: 100%; font-size: 16px; font-weight: bold; margin-top: 10px;">Update wp-config.php & Connect</button>
        <div id="dbError" style="color: #d63638; margin-top: 10px; display: none;"></div>
    </div>
    
    <script>
        document.getElementById('updateCredsBtn').addEventListener('click', async () => {
            const btn = document.getElementById('updateCredsBtn');
            const err = document.getElementById('dbError');
            btn.disabled = true;
            btn.innerText = 'Testing Connection...';
            err.style.display = 'none';

            const formData = new FormData();
            formData.append('db_name', document.getElementById('dbName').value.trim());
            formData.append('db_user', document.getElementById('dbUser').value.trim());
            formData.append('db_pass', document.getElementById('dbPass').value.trim());
            formData.append('db_host', document.getElementById('dbHost').value.trim());

            try {
                const res = await fetch('?action=update_db_creds', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.error) {
                    err.innerText = data.error;
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.innerText = 'Update wp-config.php & Connect';
                } else {
                    btn.innerText = 'Connected! Reloading...';
                    btn.style.background = '#00a32a';
                    setTimeout(() => window.location.reload(), 1000);
                }
            } catch (e) {
                err.innerText = 'Network error during request.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerText = 'Update wp-config.php & Connect';
            }
        });
    </script>
    <?php elseif ($needs_extraction): ?>

    <div class="card" style="text-align: center;">
        <h3 style="margin-top: 0;">Phase 0: Extract Snapshot Zip</h3>
        <p>No <code>wp-config.php</code> found. If you just uploaded the failsafe zip file, we need to extract it first.</p>
        
        <div class="form-group" style="text-align: left;">
            <label for="zipSelector">Select Zip File:</label>
            <select id="zipSelector" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                <?php echo $zip_options; ?>
            </select>
        </div>

        <button id="extractBtn" class="btn" style="width: 100%; font-size: 18px; font-weight: bold; padding: 15px;">Extract Backup Archive</button>

        <div id="extractSection" style="display:none; text-align: left;">
            <h4 style="margin-bottom:5px;">Extracting files...</h4>
            <div class="progress-bar-container" style="display:block;">
                <div class="progress-bar" id="progressBar0" style="background: #d63638;"></div>
            </div>
            <p id="progressText0" style="text-align:center; font-weight:bold; margin-top:5px;">0%</p>
        </div>

        <div id="log" style="text-align: left;">Ready to extract...</div>
    </div>

    <script>
        const extractBtn = document.getElementById('extractBtn');
        const logEl = document.getElementById('log');
        const extractSection = document.getElementById('extractSection');
        const progressBar0 = document.getElementById('progressBar0');
        const progressText0 = document.getElementById('progressText0');
        const zipSelector = document.getElementById('zipSelector');

        function log(msg) {
            logEl.innerHTML += '\n' + msg;
            logEl.scrollTop = logEl.scrollHeight;
        }

        extractBtn.addEventListener('click', () => {
            extractBtn.style.display = 'none';
            extractSection.style.display = 'block';
            log('Starting chunked extraction of ' + zipSelector.value + '...');
            processExtractionChunk(0);
        });

        async function processExtractionChunk(fileIndex) {
            try {
                const formData = new FormData();
                formData.append('zip_file', zipSelector.value);

                const response = await fetch('?action=extract_zip&file_index=' + fileIndex, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();

                if (data.error) {
                    log('ERROR: ' + data.error);
                    extractBtn.style.display = 'block';
                    extractBtn.innerText = 'Retry Extraction';
                    return;
                }

                progressBar0.style.width = data.progress + '%';
                progressText0.innerText = data.progress + '%';

                if (data.complete) {
                    log('✅ Phase 0: Extraction complete!');
                    log('Reloading to proceed with Phase 1...');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    log('Extracting... ' + data.progress + '%');
                    processExtractionChunk(data.file_index);
                }
            } catch (err) {
                log('Network error occurred. Retrying in 3 seconds...');
                setTimeout(() => processExtractionChunk(fileIndex), 3000);
            }
        }
    </script>
    
    <?php else: ?>

    <div class="card" style="text-align: center;">
        <button id="startBtn" class="btn" style="width: 100%; font-size: 18px; font-weight: bold; padding: 15px;">Start Restoration</button>

        <div id="importSection" style="display:none; text-align: left;">
            <h4 style="margin-bottom:5px;">Phase 1: Importing Database</h4>
            <div class="progress-bar-container" style="display:block;">
                <div class="progress-bar" id="progressBar1"></div>
            </div>
            <p id="progressText1" style="text-align:center; font-weight:bold; margin-top:5px;">0%</p>
        </div>

        <div id="srSection" style="display:none; text-align: left; opacity: 0.5;">
            <h4 style="margin-bottom:5px;">Phase 2: Search & Replace URLs</h4>
            <div class="progress-bar-container" style="display:block;">
                <div class="progress-bar" id="progressBar2" style="background: #00a32a;"></div>
            </div>
            <p id="progressText2" style="text-align:center; font-weight:bold; margin-top:5px;">0%</p>
        </div>

        <div id="purgeSection" style="display:none; text-align: left; margin-top: 20px; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; background: #fff;">
            <h4 style="margin-top:0; color: #d63638;">Phase 3: File System Purge (Malware Check)</h4>
            <p style="font-size: 14px; margin-bottom: 10px;">We found files on the server that were <strong>not</strong> in the original backup snapshot. These could be benign additions, or they could be rogue malware/backdoor files.</p>
            <div id="rogueFileList" style="max-height: 150px; overflow-y: auto; background: #f6f7f7; padding: 10px; border: 1px solid #e2e4e7; font-size: 12px; margin-bottom: 15px; font-family: monospace;">
                <!-- Files listed here -->
            </div>
            <button id="purgeBtn" class="btn" style="background: #d63638; width: 100%;">Purge Rogue Files</button>
            <button id="skipPurgeBtn" class="btn" style="background: #a7aaad; width: 100%; margin-top: 10px;">Keep Files & Finish</button>
        </div>

        <div id="log" style="text-align: left;">Ready to begin...</div>
        
        <div id="completeActions" style="display:none; margin-top:20px;">
            <p style="color:green; font-weight:bold; font-size: 18px;">✅ Site restored successfully!</p>
            <a href="/wp-login.php" class="btn" style="background:#00a32a;">Go to Login</a>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const startBtn = document.getElementById('startBtn');
        const logEl = document.getElementById('log');
        const completeActions = document.getElementById('completeActions');
        
        const importSection = document.getElementById('importSection');
        const progressBar1 = document.getElementById('progressBar1');
        const progressText1 = document.getElementById('progressText1');

        const srSection = document.getElementById('srSection');
        const progressBar2 = document.getElementById('progressBar2');
        const progressText2 = document.getElementById('progressText2');

        const oldUrl = document.getElementById('oldUrl');
        const newUrl = document.getElementById('newUrl');

        function log(msg) {
            logEl.innerHTML += '\n' + msg;
            logEl.scrollTop = logEl.scrollHeight;
        }

        async function processImportChunk(offset) {
            try {
                const response = await fetch('?action=restore&offset=' + offset, {
                    method: 'POST'
                });
                
                const data = await response.json();

                if (data.error) {
                    log('ERROR: ' + data.error);
                    startBtn.disabled = false;
                    return;
                }

                progressBar1.style.width = data.progress + '%';
                progressText1.innerText = data.progress + '%';

                if (data.complete) {
                    log('✅ Phase 1: Database Import complete!');
                    
                    if (oldUrl.value.trim() !== '' && newUrl.value.trim() !== '') {
                        log('Starting Phase 2: Serialized Search & Replace...');
                        srSection.style.opacity = '1';
                        processSearchReplaceChunk(0, 0);
                    } else {
                        startPhase3Scan();
                    }
                } else {
                    log('Importing... ' + data.progress + '%');
                    processImportChunk(data.offset);
                }
            } catch (err) {
                log('Network error occurred. Retrying in 3 seconds...');
                setTimeout(() => processImportChunk(offset), 3000);
            }
        }

        async function processSearchReplaceChunk(tableIndex, rowOffset) {
            try {
                const formData = new FormData();
                formData.append('step', 'search_replace');
                formData.append('old_url', oldUrl.value.trim());
                formData.append('new_url', newUrl.value.trim());
                formData.append('old_abspath', document.getElementById('oldAbspath') ? document.getElementById('oldAbspath').value.trim() : '');
                formData.append('new_abspath', document.getElementById('newAbspath') ? document.getElementById('newAbspath').value.trim() : '');
                formData.append('disable_seo', document.getElementById('disableSeo') && document.getElementById('disableSeo').checked ? '1' : '0');
                formData.append('disable_cron', document.getElementById('disableCron') && document.getElementById('disableCron').checked ? '1' : '0');

                const response = await fetch(`?action=search_replace&table_index=${tableIndex}&row_offset=${rowOffset}`, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();

                if (data.error) {
                    log('ERROR: ' + data.error);
                    startBtn.disabled = false;
                    return;
                }

                progressBar2.style.width = data.progress + '%';
                progressText2.innerText = data.progress + '%';
                
                if (data.log) {
                    log(data.log);
                }

                if (data.complete) {
                    log('✅ Phase 2: Search & Replace complete!');
                    startPhase3Scan();
                } else {
                    processSearchReplaceChunk(data.table_index, data.row_offset);
                }
            } catch (err) {
                log('Network error occurred during Search & Replace. Retrying in 3 seconds...');
                setTimeout(() => processSearchReplaceChunk(tableIndex, rowOffset), 3000);
            }
        }

        let detectedRogueFiles = [];

        async function startPhase3Scan(isFirst = true) {
            if (isFirst) log('Starting Phase 3: Scanning for rogue files...');
            try {
                const url = isFirst ? '?action=scan_rogue&reset=1' : '?action=scan_rogue';
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.error) {
                    log('Warning: ' + data.error);
                    finishRestoration();
                    return;
                }

                if (!data.complete) {
                    log(`Scanning... ${data.scanned} files checked. Found ${data.rogues_found} rogue files so far.`);
                    startPhase3Scan(false);
                    return;
                }

                if (data.rogue_files && data.rogue_files.length > 0) {
                    detectedRogueFiles = data.rogue_files;
                    log('⚠️ Detected ' + detectedRogueFiles.length + ' rogue files that were not in the backup.');
                    
                    const listEl = document.getElementById('rogueFileList');
                    listEl.innerHTML = detectedRogueFiles.map(f => `<div>${f}</div>`).join('');
                    document.getElementById('purgeSection').style.display = 'block';
                    
                    // Scroll to bottom
                    window.scrollTo(0, document.body.scrollHeight);
                } else {
                    log('✅ Phase 3: File System is clean (' + data.scanned + ' files scanned). No rogue files found.');
                    finishRestoration();
                }
            } catch (err) {
                log('Warning: Network error during file system scan. Skipping purge phase.');
                finishRestoration();
            }
        }

        document.getElementById('purgeBtn').addEventListener('click', async () => {
            const btn = document.getElementById('purgeBtn');
            const skipBtn = document.getElementById('skipPurgeBtn');
            btn.disabled = true;
            skipBtn.disabled = true;
            btn.innerText = 'Purging...';
            log('Purging ' + detectedRogueFiles.length + ' rogue files...');
            
            try {
                const formData = new FormData();
                formData.append('files', JSON.stringify(detectedRogueFiles));
                
                const response = await fetch('?action=purge_rogue', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    log('✅ Phase 3: Purged ' + data.deleted_count + ' rogue files successfully.');
                    if (data.errors && data.errors.length > 0) {
                        log('Warning: ' + data.errors.length + ' files could not be deleted due to permissions.');
                    }
                } else {
                    log('ERROR: ' + (data.error || 'Failed to purge files.'));
                }
            } catch (err) {
                log('ERROR: Network issue during purge.');
            }
            
            document.getElementById('purgeSection').style.display = 'none';
            finishRestoration();
        });

        document.getElementById('skipPurgeBtn').addEventListener('click', () => {
            log('Skipping file system purge. Rogue files were kept.');
            document.getElementById('purgeSection').style.display = 'none';
            finishRestoration();
        });

        async function finishRestoration() {
            log('Cleaning up files...');
            try {
                const formData = new FormData();
                if (document.getElementById('newUrl')) formData.append('new_url', document.getElementById('newUrl').value.trim());
                if (document.getElementById('disableEmails')) formData.append('disable_emails', document.getElementById('disableEmails').checked ? '1' : '0');

                await fetch('?action=cleanup', { method: 'POST', body: formData });
                log('Cleanup finished. The script and SQL file have been deleted for security.');
            } catch (e) {
                log('Cleanup failed, please delete database-backup.sql and emergency-restore.php manually.');
            }
            completeActions.style.display = 'block';
        }

        if (startBtn) {
            startBtn.addEventListener('click', () => {
                startBtn.disabled = true;
                oldUrl.disabled = true;
                newUrl.disabled = true;
                importSection.style.display = 'block';
                if (oldUrl.value.trim() !== '' && newUrl.value.trim() !== '') {
                    srSection.style.display = 'block';
                }
                log('Starting chunked database import...');
                processImportChunk(0);
            });
        }
    </script>
</body>
</html>
