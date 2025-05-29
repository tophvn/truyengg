<?php
class OTruyenAPI {
    private $base_url = 'https://otruyenapi.com/v1/api';
    private $cdn_image = 'https://img.otruyenapi.com';
    private function fetchData($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("HTTP Error: $httpCode for URL: $url");
            return ['status' => 'error', 'msg' => "HTTP Error: $httpCode"];
        }

        $data = json_decode($response, true);
        if (!$data) {
            error_log("Invalid JSON response for URL: $url");
            return ['status' => 'error', 'msg' => 'Invalid JSON response'];
        }

        return $data;
    }

    public function getChapterData($chapter_api_url) {
        return $this->fetchData($chapter_api_url);
    }

    public function getHomeComics($page = 1, $per_page = 24) {
        $url = $this->base_url . '/home?page=' . urlencode($page) . '&limit=' . urlencode($per_page);
        error_log("Fetching home comics: $url");
        return $this->fetchData($url);
    }

    public function getCategories() {
        return $this->fetchData($this->base_url . '/the-loai');
    }

    public function getComicsByCategory($slug, $page = 1, $per_page = 24) {
        $url = $this->base_url . '/the-loai/' . urlencode($slug) . '?page=' . urlencode($page) . '&limit=' . urlencode($per_page);
        error_log("Fetching comics for category: $url");
        return $this->fetchData($url);
    }

    public function getComicDetails($slug) {
        return $this->fetchData($this->base_url . '/truyen-tranh/' . $slug);
    }

    public function searchComics($query) {
        return $this->fetchData($this->base_url . '/tim-kiem?keywords=' . urlencode($query));
    }

    public function getComicList($type, $page = 1, $per_page = 24) {
        $url = $this->base_url . '/danh-sach/' . urlencode($type) . '?page=' . urlencode($page) . '&limit=' . urlencode($per_page);
        error_log("Fetching comic list: $url");
        return $this->fetchData($url);
    }

    public function getImageUrl($thumb_url) {
        if (empty($thumb_url)) {
            return 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
        }
        return $this->cdn_image . '/uploads/comics/' . $thumb_url;
    }

    public function getTag($chapter_count) {
        if ($chapter_count > 50) {
            return 'Hot';
        } elseif ($chapter_count < 10) {
            return 'Mới';
        }
        return '';
    }

    public function advancedSearch($params) {
        $query = [];
        if (!empty($params['genres'])) {
            $query[] = 'genres=' . urlencode(implode(',', $params['genres']));
        }
        if (!empty($params['notgenres'])) {
            $query[] = 'notgenres=' . urlencode(implode(',', $params['notgenres']));
        }
        if (isset($params['country']) && $params['country'] !== '0') {
            $query[] = 'country=' . urlencode($params['country']);
        }
        if (isset($params['status']) && $params['status'] !== '-1') {
            $query[] = 'status=' . urlencode($params['status']);
        }
        if (isset($params['minchapter']) && $params['minchapter'] !== '0') {
            $query[] = 'minchapter=' . urlencode($params['minchapter']);
        }
        if (isset($params['sort']) && $params['sort'] !== '0') {
            $query[] = 'sort=' . urlencode($params['sort']);
        }
        $query[] = 'page=' . urlencode($params['page'] ?? 1);
        $query[] = 'limit=24';
        $url = $this->base_url . '/tim-kiem?' . implode('&', $query);
        error_log("Advanced Search URL: $url");
        $response = $this->fetchData($url);
        error_log("Advanced Search Response: " . json_encode($response));
        return $response;
    }
}

if (isset($_GET['action'])) {
    $api = new OTruyenAPI();
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'home':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $per_page = 24;
            echo json_encode($api->getHomeComics($page, $per_page));
            break;
        case 'categories':
            echo json_encode($api->getCategories());
            break;
        case 'category':
            if (isset($_GET['slug'])) {
                $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                $per_page = 24;
                echo json_encode($api->getComicsByCategory($_GET['slug'], $page, $per_page));
            }
            break;
        case 'comic':
            if (isset($_GET['slug'])) {
                echo json_encode($api->getComicDetails($_GET['slug']));
            }
            break;
        case 'search':
            if (isset($_GET['query'])) {
                echo json_encode($api->searchComics($_GET['query']));
            }
            break;
        case 'list':
            if (isset($_GET['type'])) {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $per_page = 24;
                echo json_encode($api->getComicList($_GET['type'], $page, $per_page));
            }
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>