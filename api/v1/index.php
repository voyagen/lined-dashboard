<?php
require_once '../../autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class ApiController {
    private $ipReputationRepository;

    public function __construct() {
        try {
            $this->ipReputationRepository = new IpReputationRepository();
        } catch (Exception $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
            exit;
        }
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? 'dashboard';

        try {
            switch ($action) {
                case 'dashboard':
                    $this->getDashboardData();
                    break;
                case 'timeline':
                    $this->getTimelineData();
                    break;
                case 'stats':
                    $this->getStats();
                    break;
                case 'ip':
                    $ip = $_GET['ip'] ?? null;
                    $this->getIpData($ip);
                    break;
                default:
                    $this->getDashboardData();
            }
        } catch (Exception $e) {
            $this->sendError('API Error: ' . $e->getMessage());
        }
    }

    private function getDashboardData() {
        // Get timeline data for charts
        $timelineData = $this->ipReputationRepository->getTimelineData();
        
        // Convert models to arrays for JSON
        $formattedData = [];
        foreach ($timelineData as $ip => $models) {
            $formattedData[$ip] = [];
            foreach ($models as $model) {
                $formattedData[$ip][] = $model->toArray();
            }
        }
        
        // Get dashboard statistics
        $stats = $this->ipReputationRepository->getDashboardStats();
        
        $this->sendSuccess([
            'data' => $formattedData,
            'stats' => $stats,
            'raw_data' => $this->flattenData($formattedData)
        ]);
    }

    private function getTimelineData() {
        $timelineData = $this->ipReputationRepository->getTimelineData();
        
        $formattedData = [];
        foreach ($timelineData as $ip => $models) {
            $formattedData[$ip] = [];
            foreach ($models as $model) {
                $formattedData[$ip][] = [
                    'datetime' => $model->getDatetime(),
                    'status' => $model->getStatus(),
                    'mails_send' => $model->getMailsSend(),
                    'mails_delivered' => $model->getMailsDelivered(),
                    'complain_rate' => $model->getComplainRate(),
                    'delivery_rate' => $model->getDeliveryRate(),
                    'status_color' => $model->getStatusColor(),
                    'status_priority' => $model->getStatusPriority(),
                    'is_healthy' => $model->isHealthy(),
                    'has_issues' => $model->hasIssues()
                ];
            }
        }
        
        $this->sendSuccess([
            'timeline_data' => $formattedData,
            'unique_ips' => $this->ipReputationRepository->getUniqueIps()
        ]);
    }

    private function getStats() {
        $stats = $this->ipReputationRepository->getDashboardStats();
        
        // Add additional analytics
        $stats['problematic_ips'] = $this->ipReputationRepository->getProblematicIps();
        $stats['healthy_ips'] = $this->ipReputationRepository->getHealthyIps();
        
        // Add per-IP statistics
        $ipStats = [];
        foreach ($this->ipReputationRepository->getUniqueIps() as $ip) {
            $ipStats[$ip] = [
                'total_sent' => $this->ipReputationRepository->getTotalEmailsSent($ip),
                'total_delivered' => $this->ipReputationRepository->getTotalEmailsDelivered($ip),
                'delivery_rate' => $this->ipReputationRepository->getAverageDeliveryRate($ip),
                'status_distribution' => $this->ipReputationRepository->getStatusDistributionByIp($ip)
            ];
        }
        $stats['ip_statistics'] = $ipStats;
        
        $this->sendSuccess($stats);
    }

    private function getIpData($ip) {
        if (!$ip) {
            $this->sendError('IP parameter is required');
            return;
        }

        $ipData = $this->ipReputationRepository->findByIp($ip);
        
        if (empty($ipData)) {
            $this->sendError('No data found for IP: ' . $ip);
            return;
        }

        $formattedData = [];
        foreach ($ipData as $model) {
            $formattedData[] = $model->toArray();
        }

        $this->sendSuccess([
            'ip' => $ip,
            'data' => $formattedData,
            'total_records' => count($formattedData),
            'statistics' => [
                'total_sent' => $this->ipReputationRepository->getTotalEmailsSent($ip),
                'total_delivered' => $this->ipReputationRepository->getTotalEmailsDelivered($ip),
                'delivery_rate' => $this->ipReputationRepository->getAverageDeliveryRate($ip),
                'status_distribution' => $this->ipReputationRepository->getStatusDistributionByIp($ip)
            ]
        ]);
    }

    private function flattenData($groupedData) {
        $flattened = [];
        foreach ($groupedData as $ip => $records) {
            foreach ($records as $record) {
                $flattened[] = $record;
            }
        }
        return $flattened;
    }

    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ], JSON_PRETTY_PRINT);
    }

    private function sendError($message) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $message
        ], JSON_PRETTY_PRINT);
    }
}

// Handle the API request
$api = new ApiController();
$api->handleRequest();
?>