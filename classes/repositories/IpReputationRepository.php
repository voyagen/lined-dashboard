<?php

class IpReputationRepository extends Repository {
    
    protected function setTableName() {
        $this->table = 'ip_reputation';
    }

    protected function setModelClass() {
        $this->modelClass = 'IpReputation';
    }

    /**
     * Find by IP address
     * @param string $ip
     * @return array Returns an array of IpReputation model instances ordered by datetime
     */
    public function findByIp($ip) {
        return $this->findBy(['ip' => $ip], 'datetime ASC');
    }


    /**
     * Get all records grouped by IP address
     * @return array Returns an associative array where keys are IP addresses and values are arrays of IpReputation model instances
     */
    public function getAllGroupedByIp() {
        $sql = "SELECT * FROM {$this->table} ORDER BY ip, datetime";
        $dataArray = $this->db->fetchAll($sql);
        
        $grouped = [];
        foreach ($dataArray as $data) {
            $model = $this->createModel($data);
            $ip = $model->getIp();
            
            if (!isset($grouped[$ip])) {
                $grouped[$ip] = [];
            }
            
            $grouped[$ip][] = $model;
        }
        
        return $grouped;
    }

    /**
     * Get unique IP addresses from the database
     * @return array Returns an array of unique IP addresses ordered alphabetically
     */
    public function getUniqueIps() {
        $sql = "SELECT DISTINCT ip FROM {$this->table} ORDER BY ip";
        $result = $this->db->fetchAll($sql);
        
        return array_column($result, 'ip');
    }

    /**
     * Get the count of all records in the table
     * @return int Returns the total count of records
     */
    public function getStatusDistribution() {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status";
        $result = $this->db->fetchAll($sql);
        
        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row['status']] = (int)$row['count'];
        }
        
        return $distribution;
    }

    /**
     * Get the count of records grouped by status for a specific IP address
     * @param string $ip
     * @return array Returns an associative array where keys are statuses and values are counts
     */
    public function getStatusDistributionByIp($ip) {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table} WHERE ip = ? GROUP BY status";
        $result = $this->db->fetchAll($sql, [$ip]);
        
        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row['status']] = (int)$row['count'];
        }
        
        return $distribution;
    }

    /**
     * Get the total number of emails sent
     * @param string|null $ip If provided, filter by IP address
     * @return int Returns the total number of emails sent
     */
    public function getTotalEmailsSent($ip = null) {
        if ($ip) {
            $sql = "SELECT SUM(mails_send) as total FROM {$this->table} WHERE ip = ?";
            $result = $this->db->fetchOne($sql, [$ip]);
        } else {
            $sql = "SELECT SUM(mails_send) as total FROM {$this->table}";
            $result = $this->db->fetchOne($sql);
        }
        
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get the total number of emails delivered
     * @param string|null $ip If provided, filter by IP address
     * @return int Returns the total number of emails delivered
     */
    public function getTotalEmailsDelivered($ip = null) {
        if ($ip) {
            $sql = "SELECT SUM(mails_delivered) as total FROM {$this->table} WHERE ip = ?";
            $result = $this->db->fetchOne($sql, [$ip]);
        } else {
            $sql = "SELECT SUM(mails_delivered) as total FROM {$this->table}";
            $result = $this->db->fetchOne($sql);
        }
        
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get the average delivery rate as a percentage
     * @param string|null $ip If provided, filter by IP address
     * @return float Returns the average delivery rate as a percentage, rounded to 2 decimal places
     */
    public function getAverageDeliveryRate($ip = null) {
        if ($ip) {
            $sql = "SELECT AVG((mails_delivered / mails_send) * 100) as avg_rate FROM {$this->table} WHERE ip = ? AND mails_send > 0";
            $result = $this->db->fetchOne($sql, [$ip]);
        } else {
            $sql = "SELECT AVG((mails_delivered / mails_send) * 100) as avg_rate FROM {$this->table} WHERE mails_send > 0";
            $result = $this->db->fetchOne($sql);
        }
        
        return round($result['avg_rate'] ?? 0, 2);
    }

    /**
     * Get the latest status for each IP address
     * @return array Returns an array of IpReputation model instances with the latest status for each IP
     */
    public function getLatestStatusByIp() {
        $sql = "SELECT ip, status, datetime, mails_send, mails_delivered 
                FROM {$this->table} t1
                WHERE datetime = (
                    SELECT MAX(datetime) 
                    FROM {$this->table} t2 
                    WHERE t2.ip = t1.ip
                )
                ORDER BY ip";
        
        $dataArray = $this->db->fetchAll($sql);
        return $this->createModelCollection($dataArray);
    }


    /**
     * Get problematic IPs based on complaint and failure rates
     * @param float $minComplaintRate Minimum complaint rate to consider an IP problematic
     * @param int $minFailureRate Minimum failure rate (percentage) to consider an IP problematic
     * @return array Returns an array of IP addresses that are considered problematic
     */
    public function getProblematicIps($minComplaintRate = 0.5, $minFailureRate = 10) {
        $sql = "SELECT DISTINCT ip FROM {$this->table} 
                WHERE complain_rate >= ? 
                OR (mails_send > 0 AND ((mails_send - mails_delivered) / mails_send * 100) >= ?)
                OR status = 'RED'";
        
        $result = $this->db->fetchAll($sql, [$minComplaintRate, $minFailureRate]);
        return array_column($result, 'ip');
    }

    /**
     * Get healthy IPs based on complaint and failure rates
     * @param float $maxComplaintRate Maximum complaint rate to consider an IP healthy
     * @param int $maxFailureRate Maximum failure rate (percentage) to consider an IP healthy
     * @return array Returns an array of IP addresses that are considered healthy
     */
    public function getHealthyIps($maxComplaintRate = 0.1, $maxFailureRate = 5) {
        $sql = "SELECT DISTINCT ip FROM {$this->table} t1
                WHERE ip NOT IN (
                    SELECT DISTINCT ip FROM {$this->table}
                    WHERE complain_rate > ? 
                    OR (mails_send > 0 AND ((mails_send - mails_delivered) / mails_send * 100) > ?)
                    OR status = 'RED'
                )";
        
        $result = $this->db->fetchAll($sql, [$maxComplaintRate, $maxFailureRate]);
        return array_column($result, 'ip');
    }


    /**
     * Get timeline data for the dashboard
     * @return array Returns an associative array where keys are IP addresses and values are arrays of IpReputation model instances
     */
    public function getTimelineData() {
        return $this->getAllGroupedByIp();
    }

    /**
     * Get dashboard statistics
     * @return array Returns an associative array with various statistics for the dashboard
     */
    public function getDashboardStats() {
        $stats = [
            'total_ips' => count($this->getUniqueIps()),
            'total_measurements' => $this->count(),
            'total_emails_sent' => $this->getTotalEmailsSent(),
            'total_emails_delivered' => $this->getTotalEmailsDelivered(),
            'status_distribution' => $this->getStatusDistribution(),
            'average_delivery_rate' => $this->getAverageDeliveryRate(),
            'latest_status_by_ip' => []
        ];

        $latestStatuses = $this->getLatestStatusByIp();
        foreach ($latestStatuses as $status) {
            $stats['latest_status_by_ip'][$status->getIp()] = [
                'status' => $status->getStatus(),
                'datetime' => $status->getDatetime(),
                'mails_send' => $status->getMailsSend(),
                'mails_delivered' => $status->getMailsDelivered(),
                'delivery_rate' => $status->getDeliveryRate()
            ];
        }

        return $stats;
    }

}