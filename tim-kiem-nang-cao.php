<?php
require_once 'includes/layouts/header.php';
require_once 'api/otruyen.php';

// Hàm tính khoảng thời gian từ ngày cập nhật
function timeAgo($dateString) {
    if (empty($dateString)) return 'Chưa cập nhật';
    try {
        $updateTime = new DateTime($dateString);
        $currentTime = new DateTime();
        $interval = $currentTime->diff($updateTime);
        if ($interval->y > 0) return $interval->y . ' năm trước';
        elseif ($interval->m > 0) return $interval->m . ' tháng trước';
        elseif ($interval->d > 0) return $interval->d . ' ngày trước';
        elseif ($interval->h > 0) return $interval->h . ' giờ trước';
        elseif ($interval->i > 0) return $interval->i . ' phút trước';
        else return 'Vừa xong';
    } catch (Exception $e) {
        return 'Chưa cập nhật';
    }
}

$api = new OTruyenAPI();

// Fetch genres from API
$genres_data = $api->getCategories();
$genres = isset($genres_data['data']['items']) ? $genres_data['data']['items'] : [];
$genre_map = []; // Map slug to name for display
foreach ($genres as $genre) {
    $genre_map[$genre['slug']] = $genre['name'];
}

// Get current page from URL query string
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$comics_per_page = 24;

// Handle filter parameters from GET request
$filters = [
    'genres' => isset($_GET['genres']) && !empty(trim($_GET['genres'])) ? array_filter(explode(',', trim($_GET['genres'])), 'strlen') : [],
    'notgenres' => isset($_GET['notgenres']) && !empty(trim($_GET['notgenres'])) ? array_filter(explode(',', trim($_GET['notgenres'])), 'strlen') : [],
    'country' => isset($_GET['country']) ? trim($_GET['country']) : '0',
    'status' => isset($_GET['status']) ? trim($_GET['status']) : '-1',
    'minchapter' => isset($_GET['minchapter']) ? trim($_GET['minchapter']) : '0',
    'sort' => isset($_GET['sort']) ? trim($_GET['sort']) : '0',
    'page' => $current_page
];

// Debug: Log filters to check input
error_log('Filters: ' . json_encode($filters));

// Map country to genres
$country_to_genres = [
    '1' => ['manhua'],              // Trung Quốc
    '2' => ['manhwa', 'webtoon'],   // Hàn Quốc
    '3' => ['manga', 'anime'],      // Nhật Bản
    '4' => ['viet-nam'],            // Việt Nam
];
$country_names = [
    '1' => 'Trung Quốc',
    '2' => 'Hàn Quốc',
    '3' => 'Nhật Bản',
    '4' => 'Việt Nam',
];

// Map status to API type
$status_to_type = [
    '0' => 'dang-phat-hanh', // Đang tiến hành
    '2' => 'hoan-thanh',     // Hoàn thành
];
$status_names = [
    '0' => 'Đang tiến hành',
    '2' => 'Hoàn thành',
];

// Add country genres to filters['genres']
$effective_genres = $filters['genres'];
if ($filters['country'] !== '0' && isset($country_to_genres[$filters['country']])) {
    $effective_genres = array_unique(array_merge($effective_genres, $country_to_genres[$filters['country']]));
}

// Cache for comic genres
$comic_genres_cache = [];

// Function to filter comics by notgenres
function filterNotGenres($comics, $notgenres, $api, &$comic_genres_cache) {
    if (empty($notgenres)) {
        return $comics;
    }
    $filtered_comics = [];
    foreach ($comics as $comic) {
        $slug = $comic['slug'];
        // Check if genres are in items
        $comic_genres = [];
        if (isset($comic['genres']) && is_array($comic['genres'])) {
            $comic_genres = array_column($comic['genres'], 'slug');
        } elseif (!isset($comic_genres_cache[$slug])) {
            // Fetch from API
            $comic_details = $api->getComicDetails($slug);
            $comic_genres_cache[$slug] = [];
            if (isset($comic_details['status']) && $comic_details['status'] === 'success' && isset($comic_details['data']['item']['genres']) && is_array($comic_details['data']['item']['genres'])) {
                foreach ($comic_details['data']['item']['genres'] as $genre) {
                    if (isset($genre['slug'])) {
                        $comic_genres_cache[$slug][] = $genre['slug'];
                    }
                }
            }
            $comic_genres = $comic_genres_cache[$slug];
        } else {
            $comic_genres = $comic_genres_cache[$slug];
        }
        // Exclude comic if it has any notgenres
        $has_notgenre = false;
        foreach ($notgenres as $notgenre) {
            if (in_array($notgenre, $comic_genres)) {
                $has_notgenre = true;
                break;
            }
        }
        if (!$has_notgenre) {
            $filtered_comics[] = $comic;
        }
    }
    return $filtered_comics;
}

// Function to fetch comics by status
function fetchComicsByStatus($status_type, $api) {
    $comics = [];
    $page = 1;
    $total_pages = 1;
    while ($page <= $total_pages) {
        $comics_data = $api->getComicList("$status_type?page=$page");
        error_log("Status $status_type Page $page API Response: " . json_encode($comics_data));
        if (isset($comics_data['status']) && $comics_data['status'] === 'success' && isset($comics_data['data']['items']) && is_array($comics_data['data']['items'])) {
            $comics = array_merge($comics, $comics_data['data']['items']);
            $total_pages = isset($comics_data['data']['params']['pagination']['totalPages']) ? (int)$comics_data['data']['params']['pagination']['totalPages'] : 1;
            $page++;
        } else {
            error_log("Status $status_type Page $page API Error: " . (isset($comics_data['msg']) ? $comics_data['msg'] : 'Không thể tải dữ liệu'));
            break;
        }
    }
    return $comics;
}

// Fetch comics based on filters
$comics = [];
$total_comics = 0;
$error_message = null;

if ($filters['status'] !== '-1' && empty($effective_genres) && empty($filters['notgenres']) && $filters['minchapter'] === '0' && $filters['sort'] === '0') {
    // Only status filter
    $status_type = $status_to_type[$filters['status']];
    $all_comics = fetchComicsByStatus($status_type, $api);
    // Process comics
    $unique_comics = [];
    $seen_slugs = [];
    foreach ($all_comics as $comic) {
        if (!in_array($comic['slug'], $seen_slugs) && isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
            $seen_slugs[] = $comic['slug'];
            $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
            $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
            $unique_comics[] = $comic;
        }
    }
    // Sort by updatedAt (default: descending)
    usort($unique_comics, function($a, $b) {
        $a_time = $a['updatedAt'] ? strtotime($a['updatedAt']) : 0;
        $b_time = $b['updatedAt'] ? strtotime($b['updatedAt']) : 0;
        return $b_time - $a_time;
    });
    // Paginate
    $total_comics = count($unique_comics);
    $offset = ($current_page - 1) * $comics_per_page;
    $comics = array_slice($unique_comics, $offset, $comics_per_page);
    error_log('Status Only Total Comics: ' . $total_comics . ', Offset: ' . $offset . ', Comics Count: ' . count($comics));
} elseif (count($effective_genres) === 1 && empty($filters['notgenres']) && $filters['minchapter'] === '0' && $filters['sort'] === '0' && $filters['status'] === '-1') {
    // Single genre (including country), no other filters
    $comics_data = $api->getComicsByCategory($effective_genres[0], $current_page, $comics_per_page);
    error_log('Single Genre API Response: ' . json_encode($comics_data));
    if (isset($comics_data['status']) && $comics_data['status'] === 'success' && isset($comics_data['data']['items']) && is_array($comics_data['data']['items'])) {
        foreach ($comics_data['data']['items'] as $comic) {
            if (isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
                $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
                $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
                $comics[] = $comic;
            }
        }
        $total_comics = isset($comics_data['data']['params']['pagination']['totalItems']) ? (int)$comics_data['data']['params']['pagination']['totalItems'] : count($comics);
    } else {
        $error_message = isset($comics_data['msg']) ? $comics_data['msg'] : 'Không thể tải dữ liệu từ API';
        error_log('Single Genre API Error: ' . $error_message);
    }
} elseif (!empty($effective_genres) && $filters['minchapter'] === '0' && $filters['sort'] === '0' && $filters['status'] === '-1') {
    // Multiple genres (including country), no minchapter or sort
    $all_comics = [];
    foreach ($effective_genres as $genre) {
        $page = 1;
        $total_pages_genre = 1;
        while ($page <= $total_pages_genre) {
            $comics_data = $api->getComicsByCategory($genre, $page, $comics_per_page);
            error_log("Genre $genre Page $page API Response: " . json_encode($comics_data));
            if (isset($comics_data['status']) && $comics_data['status'] === 'success' && isset($comics_data['data']['items']) && is_array($comics_data['data']['items'])) {
                $all_comics = array_merge($all_comics, $comics_data['data']['items']);
                $total_pages_genre = isset($comics_data['data']['params']['pagination']['totalPages']) ? (int)$comics_data['data']['params']['pagination']['totalPages'] : 1;
                $page++;
            } else {
                error_log("Genre $genre Page $page API Error: " . (isset($comics_data['msg']) ? $comics_data['msg'] : 'Không thể tải dữ liệu'));
                break;
            }
        }
    }
    // Remove duplicates based on slug
    $unique_comics = [];
    $seen_slugs = [];
    foreach ($all_comics as $comic) {
        if (!in_array($comic['slug'], $seen_slugs) && isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
            $seen_slugs[] = $comic['slug'];
            $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
            $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
            $unique_comics[] = $comic;
        }
    }
    // Filter notgenres
    $unique_comics = filterNotGenres($unique_comics, $filters['notgenres'], $api, $comic_genres_cache);
    // Sort by updatedAt (default: descending)
    usort($unique_comics, function($a, $b) {
        $a_time = $a['updatedAt'] ? strtotime($a['updatedAt']) : 0;
        $b_time = $b['updatedAt'] ? strtotime($b['updatedAt']) : 0;
        return $b_time - $a_time;
    });
    // Paginate
    $total_comics = count($unique_comics);
    $offset = ($current_page - 1) * $comics_per_page;
    $comics = array_slice($unique_comics, $offset, $comics_per_page);
    error_log('Multiple Genres Total Comics: ' . $total_comics . ', Offset: ' . $offset . ', Comics Count: ' . count($comics));
} elseif ($filters['status'] !== '-1' && (!empty($effective_genres) || !empty($filters['notgenres']))) {
    // Status with genres or notgenres
    $all_comics = [];
    if (!empty($effective_genres)) {
        // Fetch comics for each genre (OR logic)
        foreach ($effective_genres as $genre) {
            $page = 1;
            $total_pages_genre = 1;
            while ($page <= $total_pages_genre) {
                $comics_data = $api->getComicsByCategory($genre, $page, $comics_per_page);
                error_log("Genre $genre Page $page API Response: " . json_encode($comics_data));
                if (isset($comics_data['status']) && $comics_data['status'] === 'success' && isset($comics_data['data']['items']) && is_array($comics_data['data']['items'])) {
                    $all_comics = array_merge($all_comics, $comics_data['data']['items']);
                    $total_pages_genre = isset($comics_data['data']['params']['pagination']['totalPages']) ? (int)$comics_data['data']['params']['pagination']['totalPages'] : 1;
                    $page++;
                } else {
                    error_log("Genre $genre Page $page API Error: " . (isset($comics_data['msg']) ? $comics_data['msg'] : 'Không thể tải dữ liệu'));
                    break;
                }
            }
        }
    } else {
        // If no genres, fetch all comics by status
        $status_type = $status_to_type[$filters['status']];
        $all_comics = fetchComicsByStatus($status_type, $api);
    }
    // Apply status filter (AND logic)
    if (!empty($effective_genres)) {
        $status_type = $status_to_type[$filters['status']];
        $status_comics = fetchComicsByStatus($status_type, $api);
        $status_slugs = array_column($status_comics, 'slug');
        $all_comics = array_filter($all_comics, function($comic) use ($status_slugs) {
            return in_array($comic['slug'], $status_slugs);
        });
    }
    // Remove duplicates and process
    $unique_comics = [];
    $seen_slugs = [];
    foreach ($all_comics as $comic) {
        if (!in_array($comic['slug'], $seen_slugs) && isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
            $seen_slugs[] = $comic['slug'];
            $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
            $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
            $unique_comics[] = $comic;
        }
    }
    // Filter notgenres
    $unique_comics = filterNotGenres($unique_comics, $filters['notgenres'], $api, $comic_genres_cache);
    // Sort by updatedAt (default: descending)
    usort($unique_comics, function($a, $b) {
        $a_time = $a['updatedAt'] ? strtotime($a['updatedAt']) : 0;
        $b_time = $b['updatedAt'] ? strtotime($b['updatedAt']) : 0;
        return $b_time - $a_time;
    });
    // Paginate
    $total_comics = count($unique_comics);
    $offset = ($current_page - 1) * $comics_per_page;
    $comics = array_slice($unique_comics, $offset, $comics_per_page);
    error_log('Status with Genres Total Comics: ' . $total_comics . ', Offset: ' . $offset . ', Comics Count: ' . count($comics));
} else {
    // Other filters, use advancedSearch
    $comics_data = $api->advancedSearch($filters);
    error_log('Advanced Search API Response: ' . json_encode($comics_data));
    if (isset($comics_data['status']) && $comics_data['status'] === 'success' && isset($comics_data['data']['items']) && is_array($comics_data['data']['items'])) {
        foreach ($comics_data['data']['items'] as $comic) {
            if (isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
                $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
                $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
                $comics[] = $comic;
            }
        }
        $total_comics = isset($comics_data['data']['params']['pagination']['totalItems']) ? (int)$comics_data['data']['params']['pagination']['totalItems'] : count($comics);
        // Apply status filter if needed
        if ($filters['status'] !== '-1') {
            $status_type = $status_to_type[$filters['status']];
            $status_comics = fetchComicsByStatus($status_type, $api);
            $status_slugs = array_column($status_comics, 'slug');
            $comics = array_filter($comics, function($comic) use ($status_slugs) {
                return in_array($comic['slug'], $status_slugs);
            });
            $total_comics = count($comics); // Update total after filtering
            // Re-paginate
            $offset = ($current_page - 1) * $comics_per_page;
            $comics = array_slice($comics, $offset, $comics_per_page);
        }
        // Apply notgenres filter
        $comics = filterNotGenres($comics, $filters['notgenres'], $api, $comic_genres_cache);
        $total_comics = count($comics); // Update total after filtering
        // Re-paginate
        $offset = ($current_page - 1) * $comics_per_page;
        $comics = array_slice($comics, $offset, $comics_per_page);
    } else {
        $error_message = isset($comics_data['msg']) ? $comics_data['msg'] : 'Không thể tải dữ liệu từ API';
        error_log('Advanced Search API Error: ' . $error_message);
    }
}

$total_pages = max(1, ceil($total_comics / $comics_per_page));

// Build search result title
$search_title = "Kết Quả Tìm Kiếm - ($total_comics truyện)";

// Fetch upcoming comics for display
$upcoming_comics_data = $api->getComicList('sap-ra-mat?page=1');
$upcoming_comics = [];
if (isset($upcoming_comics_data['status']) && $upcoming_comics_data['status'] === 'success' && isset($upcoming_comics_data['data']['items']) && is_array($upcoming_comics_data['data']['items'])) {
    foreach ($upcoming_comics_data['data']['items'] as $comic) {
        if (isset($comic['slug'], $comic['name'], $comic['thumb_url']) && !empty($comic['slug']) && !empty($comic['name']) && !empty($comic['thumb_url'])) {
            $comic['updatedAt'] = isset($comic['updatedAt']) ? $comic['updatedAt'] : (isset($comic['updated_at']) ? $comic['updated_at'] : '');
            $comic['chaptersLatest'] = isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) ? $comic['chaptersLatest'] : [['chapter_name' => 'N/A']];
            $upcoming_comics[] = $comic;
        }
    }
    $upcoming_comics = array_slice($upcoming_comics, 0, 6);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm Kiếm Nâng Cao - TruyenGG</title>
    <link rel="apple-touch-icon" href="https://st.truyengg.net/template/frontend/images/apple-touch-icon.png" />
    <link rel="shortcut icon" href="https://st.truyengg.net/template/frontend/images/favicon.ico" type="image/x-icon" />
    <link rel="icon" href="https://st.truyengg.net/template/frontend/images/favicon.ico" type="image/x-icon">
    <link rel="alternate" type="application/atom+xml" title="TruyenGG Atom Feed - Rss" href="https://truyengg.net/rss.html" />
    <meta name="copyright" content="Copyright © 2024 TruyenGG.Net" />
    <meta name="Author" content="TruyenGG" />
    <link rel="preload" href="https://st.truyengg.net/template/frontend/fonts/quicksand-v8-vietnamese_latin-ext_latin-regular.woff2" as="font" type="font/woff" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://st.truyengg.net/template/frontend/css/custom.css?v=5.6" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.0.1/js/bootstrap.min.js" integrity="sha512-EKWWs1ZcA2ZY9lbLISPz8aGR2+L7JVYqBAYTq5AXgBkSjRSuQEGqWx8R1zAX16KdXPaCjOCaKE8MCpU0wcHlHA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://st.truyengg.net/template/frontend/js/readmore.min.js" type="text/javascript"></script>
    <script src="https://st.truyengg.net/template/frontend/js/jquery.lazy.min.js?v=1" type="text/javascript"></script>
</head>
<body class="light-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <h1 class="title_cate mr-auto">Tìm Kiếm Nâng Cao</h1>
            </div>
            <section class="mb40 box-filter">
                <div class="story-list-bl01">
                    <div class="text-center">
                        <button type="button" class="btn btn-info btn-collapse">
                            <span class="show-text hidden">Hiển thị </span>
                            <span class="hide-text">Ẩn </span> khung tìm kiếm
                        </button>
                    </div>
                    <div class="advsearch-form">
                        <form id="adv-search-form" method="GET" action="/truyengg/tim-kiem-nang-cao.php">
                            <div class="clearfix">
                                <p><span class="icon-tick"></span> Tìm trong những thể loại này</p>
                                <p><span class="icon-cross"></span> Loại trừ những thể loại này</p>
                                <p><span class="icon-checkbox"></span> Truyện có thể thuộc hoặc không thuộc thể loại này</p>
                            </div>
                            <div class="row out-button-reset mt-5">
                                <button type="button" class="btn btn-primary btn-reset" onclick="window.location.href='/truyengg/tim-kiem-nang-cao.php'"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                            </div>
                            <div class="row mb15 mt15 pt-lg-0 box-fillter-category">
                                <div class="label-search">Thể Loại</div>
                                <div class="row">
                                    <?php foreach ($genres as $genre): ?>
                                        <div class="col-md-4 col-sm-6 col-6 genre-item">
                                            <span class="icon-checkbox <?php
                                                echo in_array($genre['slug'], $filters['genres']) ? 'icon-tick' : (in_array($genre['slug'], $filters['notgenres']) ? 'icon-cross' : '');
                                            ?>" data-id="<?php echo htmlspecialchars($genre['slug']); ?>" data-type="<?php
                                                echo in_array($genre['slug'], $filters['genres']) ? 'include' : (in_array($genre['slug'], $filters['notgenres']) ? 'exclude' : 'neutral');
                                            ?>"></span>
                                            <?php echo htmlspecialchars($genre['name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <input type="hidden" name="genres" id="genres-input" value="<?php echo htmlspecialchars(implode(',', $filters['genres'])); ?>">
                                    <input type="hidden" name="notgenres" id="notgenres-input" value="<?php echo htmlspecialchars(implode(',', $filters['notgenres'])); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 col-sm-6 col-12 mb15">
                                    <div class="label-search">Quốc Gia</div>
                                    <div class="select select-search is-warning">
                                        <select class="custom-select" name="country" id="country">
                                            <option value="0" <?php echo $filters['country'] === '0' ? 'selected' : ''; ?>>Tất Cả</option>
                                            <option value="1" <?php echo $filters['country'] === '1' ? 'selected' : ''; ?>>Trung Quốc</option>
                                            <option value="2" <?php echo $filters['country'] === '2' ? 'selected' : ''; ?>>Hàn Quốc</option>
                                            <option value="3" <?php echo $filters['country'] === '3' ? 'selected' : ''; ?>>Nhật Bản</option>
                                            <option value="4" <?php echo $filters['country'] === '4' ? 'selected' : ''; ?>>Việt Nam</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6 col-12 mb15">
                                    <div class="label-search">Trạng Thái</div>
                                    <div class="select select-search is-warning">
                                        <select class="custom-select" name="status" id="status">
                                            <option value="-1" <?php echo $filters['status'] === '-1' ? 'selected' : ''; ?>>Tất Cả</option>
                                            <option value="0" <?php echo $filters['status'] === '0' ? 'selected' : ''; ?>>Đang tiến hành</option>
                                            <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Hoàn thành</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6 col-12 mb15">
                                    <div class="label-search">Số Lượng Chương</div>
                                    <div class="select select-search is-warning">
                                        <select class="custom-select" name="minchapter" id="minchapter">
                                            <option value="0" <?php echo $filters['minchapter'] === '0' ? 'selected' : ''; ?>>> 0</option>
                                            <option value="50" <?php echo $filters['minchapter'] === '50' ? 'selected' : ''; ?>>>= 50</option>
                                            <option value="100" <?php echo $filters['minchapter'] === '100' ? 'selected' : ''; ?>>>= 100</option>
                                            <option value="200" <?php echo $filters['minchapter'] === '200' ? 'selected' : ''; ?>>>= 200</option>
                                            <option value="300" <?php echo $filters['minchapter'] === '300' ? 'selected' : ''; ?>>>= 300</option>
                                            <option value="400" <?php echo $filters['minchapter'] === '400' ? 'selected' : ''; ?>>>= 400</option>
                                            <option value="500" <?php echo $filters['minchapter'] === '500' ? 'selected' : ''; ?>>>= 500</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6 col-12 mb15">
                                    <div class="label-search">Sắp Xếp</div>
                                    <div class="select select-search is-warning">
                                        <select class="custom-select" name="sort" id="sort">
                                            <option value="0" <?php echo $filters['sort'] === '0' ? 'selected' : ''; ?>>Ngày đăng giảm dần</option>
                                            <option value="1" <?php echo $filters['sort'] === '1' ? 'selected' : ''; ?>>Ngày đăng tăng dần</option>
                                            <option value="2" <?php echo $filters['sort'] === '2' ? 'selected' : ''; ?>>Ngày cập nhật giảm dần</option>
                                            <option value="3" <?php echo $filters['sort'] === '3' ? 'selected' : ''; ?>>Ngày cập nhật tăng dần</option>
                                            <option value="4" <?php echo $filters['sort'] === '4' ? 'selected' : ''; ?>>Lượt xem giảm dần</option>
                                            <option value="5" <?php echo $filters['sort'] === '5' ? 'selected' : ''; ?>>Lượt xem tăng dần</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group clearfix">
                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-search is-danger">Tìm Kiếm</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <div class="d-flex mb15">
                <h2 class="title_cate mr-auto"><?php echo htmlspecialchars($search_title); ?></h2>
            </div>
            <div class="row list_item_home">
                <?php if (isset($error_message)): ?>
                    <div class="col-12 text-center">
                        <p>Lỗi: <?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php elseif (empty($comics)): ?>
                    <div class="col-12 text-center">
                        <p>Không tìm thấy truyện phù hợp với bộ lọc!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comics as $comic): ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <a href="/truyengg/truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" class="thumbblock thumb140x195">
                                    <img data-src="<?php echo htmlspecialchars($api->getImageUrl($comic['thumb_url'])); ?>" alt="<?php echo htmlspecialchars($comic['name']); ?>" class="lazy-image" style="width: 140px; height: 195px; object-fit: cover;" src="https://st.truyengg.net/template/frontend/img/loading.jpg"/>
                                </a>
                                <div class="top-notice">
                                    <span class="time-ago"><?php echo timeAgo($comic['updatedAt']); ?></span>
                                    <?php
                                    $chapter_count = 0;
                                    $latest_chapter = 'N/A';
                                    if (isset($comic['chaptersLatest']) && is_array($comic['chaptersLatest']) && !empty($comic['chaptersLatest']) && isset($comic['chaptersLatest'][0]['chapter_name'])) {
                                        $latest_chapter = $comic['chaptersLatest'][0]['chapter_name'];
                                        $chapter_count = is_numeric($latest_chapter) ? (int)$latest_chapter : 0;
                                    }
                                    $tag = $api->getTag($chapter_count);
                                    ?>
                                    <?php if ($tag): ?>
                                        <span class="type-label <?php echo strtolower($tag); ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom-notice">
                                    <?php if (isset($comic['rating']) && is_numeric($comic['rating']) && $comic['rating'] > 0): ?>
                                        <span class="rate-star"><i class="bi bi-star-fill"></i> <?php echo number_format($comic['rating'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="rate-star"><i class="bi bi-star-fill"></i> 3.5</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="/truyengg/truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" class="fs16 txt_oneline book_name" title="<?php echo htmlspecialchars($comic['name']); ?>">
                                <?php echo htmlspecialchars($comic['name']); ?>
                            </a>
                            <div>
                                <?php if ($latest_chapter !== 'N/A'): ?>
                                    <a href="/truyengg/truyen-tranh/<?php echo htmlspecialchars($comic['slug']); ?>-chap-<?php echo htmlspecialchars($latest_chapter); ?>.html" class="fs14 cl99" title="Chương <?php echo htmlspecialchars($latest_chapter); ?>">
                                        Chương <?php echo htmlspecialchars($latest_chapter); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="fs14 cl99">Chưa có chương</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination mt-4 mb20">
                    <?php if ($current_page > 1): ?>
                        <a class="page-item" href="/truyengg/tim-kiem-nang-cao.php?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>"><span aria-hidden="true">«</span></a>
                    <?php endif; ?>
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="/truyengg/tim-kiem-nang-cao.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a class="page-item" href="/truyengg/tim-kiem-nang-cao.php?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>"><span aria-hidden="true">»</span></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>

    <?php require_once 'includes/layouts/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const genreItems = document.querySelectorAll('.genre-item .icon-checkbox');
            const genresInput = document.getElementById('genres-input');
            const notGenresInput = document.getElementById('notgenres-input');
            const form = document.getElementById('adv-search-form');

            // Debug: Log form action on load
            console.log('Initial Form Action:', form.action);

            // Initialize genre items based on current filters
            genreItems.forEach(item => {
                const genreId = item.getAttribute('data-id');
                const type = item.getAttribute('data-type');
                item.classList.remove('icon-checkbox', 'icon-tick', 'icon-cross');
                if (type === 'include') {
                    item.classList.add('icon-tick');
                } else if (type === 'exclude') {
                    item.classList.add('icon-cross');
                } else {
                    item.classList.add('icon-checkbox');
                }
            });

            // Handle genre item clicks
            genreItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const genreId = this.getAttribute('data-id');
                    let type = this.getAttribute('data-type');

                    // Toggle between neutral, include, and exclude
                    if (type === 'neutral') {
                        this.setAttribute('data-type', 'include');
                        this.classList.remove('icon-checkbox', 'icon-cross');
                        this.classList.add('icon-tick');
                    } else if (type === 'include') {
                        this.setAttribute('data-type', 'exclude');
                        this.classList.remove('icon-tick', 'icon-checkbox');
                        this.classList.add('icon-cross');
                    } else {
                        this.setAttribute('data-type', 'neutral');
                        this.classList.remove('icon-cross', 'icon-tick');
                        this.classList.add('icon-checkbox');
                    }

                    // Update hidden inputs
                    updateHiddenInputs();
                });
            });

            // Function to update hidden inputs
            function updateHiddenInputs() {
                const genres = [];
                const notGenres = [];
                genreItems.forEach(g => {
                    const id = g.getAttribute('data-id');
                    const t = g.getAttribute('data-type');
                    if (t === 'include') genres.push(id);
                    else if (t === 'exclude') notGenres.push(id);
                });
                genresInput.value = genres.join(',');
                notGenresInput.value = notGenres.join(',');

                // Debug: Log genres after update
                console.log('Genres:', genresInput.value);
                console.log('Not Genres:', notGenresInput.value);
            }

            // Handle form submission
            form.addEventListener('submit', function(e) {
                updateHiddenInputs();
                console.log('Form Action Before Submit:', form.action);
                console.log('Submitting form with Genres:', genresInput.value, 'Not Genres:', notGenresInput.value);
            });

            // Handle search button click
            document.querySelector('.btn-search').addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Search Button Clicked');
                updateHiddenInputs();
                console.log('Submitting form');
                form.submit();
            });

            // Handle collapse/expand search form
            $(".story-list-bl01 .text-center .btn-collapse").click(function() {
                if ($(".story-list-bl01 .text-center .btn-collapse .show-text").hasClass('hidden')) {
                    $(".story-list-bl01 .text-center .btn-collapse .show-text").removeClass('hidden');
                    $(".story-list-bl01 .text-center .btn-collapse .hide-text").addClass('hidden');
                    $(".advsearch-form").addClass('hidden');
                } else {
                    $(".story-list-bl01 .text-center .btn-collapse .hide-text").removeClass('hidden');
                    $(".story-list-bl01 .text-center .btn-collapse .show-text").addClass('hidden');
                    $(".advsearch-form").removeClass('hidden');
                }
            });
            $('.lazy-image').Lazy({
                effect: 'fadeIn',
                effectTime: 500,
                threshold: 200,
                onError: function(element) {
                    console.log('Lazy load error for:', element.data('src'));
                    element.attr('src', 'https://st.truyengg.net/template/frontend/img/loading.jpg');
                }
            });
        });
    </script>
</body>
</html>
