<?php

class IpReputation {
    private $id;
    private $ip;
    private $datetime;
    private $mailsSend;
    private $mailsDelivered;
    private $status;
    private $complainRate;
    private $trapsHit;
    private $sampleHelo;
    private $jmrSender;

    public function __construct($data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    public function hydrate($data) {
        $this->id = $data['id'] ?? null;
        $this->ip = $data['ip'] ?? null;
        $this->datetime = $data['datetime'] ?? null;
        $this->mailsSend = $data['mails_send'] ?? 0;
        $this->mailsDelivered = $data['mails_delivered'] ?? 0;
        $this->status = $data['status'] ?? null;
        $this->complainRate = $data['complain_rate'] ?? 0.0;
        $this->trapsHit = $data['traps_hit'] ?? 0;
        $this->sampleHelo = $data['sample_helo'] ?? null;
        $this->jmrSender = $data['jmr_sender'] ?? null;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'ip' => $this->ip,
            'datetime' => $this->datetime,
            'mails_send' => $this->mailsSend,
            'mails_delivered' => $this->mailsDelivered,
            'status' => $this->status,
            'complain_rate' => $this->complainRate,
            'traps_hit' => $this->trapsHit,
            'sample_helo' => $this->sampleHelo,
            'jmr_sender' => $this->jmrSender
        ];
    }

    public function toJson() {
        return json_encode($this->toArray());
    }

    // Getters
    public function getId() {
        return $this->id;
    }

    public function getIp() {
        return $this->ip;
    }

    public function getDatetime() {
        return $this->datetime;
    }

    public function getMailsSend() {
        return $this->mailsSend;
    }

    public function getMailsDelivered() {
        return $this->mailsDelivered;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getComplainRate() {
        return $this->complainRate;
    }

    public function getTrapsHit() {
        return $this->trapsHit;
    }

    public function getSampleHelo() {
        return $this->sampleHelo;
    }

    public function getJmrSender() {
        return $this->jmrSender;
    }

    /**
     * Calculate the delivery rate as a percentage.
     */
    public function getDeliveryRate() {
        if ($this->mailsSend == 0) {
            return 0;
        }
        return round(($this->mailsDelivered / $this->mailsSend) * 100, 2);
    }

    /**
     * Calculate the failure rate as a percentage.
     */
    public function getFailureRate() {
        return 100 - $this->getDeliveryRate();
    }

    /**
     * Check if the IP reputation is healthy based on status and rates.
     */
    public function isHealthy() {
        return $this->status === 'GREEN' && 
               $this->getDeliveryRate() >= 95 && 
               $this->complainRate <= 0.1;
    }

    /**
     * Check if the IP has any issues based on status, delivery rate, complain rate, and traps hit.
     */
    public function hasIssues() {
        return $this->status === 'RED' || 
               $this->getDeliveryRate() < 90 || 
               $this->complainRate > 0.5 ||
               $this->trapsHit > 0;
    }

    public function getStatusPriority() {
        switch ($this->status) {
            case 'GREEN': return 1;
            case 'YELLOW': return 2;
            case 'RED': return 3;
            default: return 0;
        }
    }

    /**
     * Get the color associated with the status for UI representation.
     */
    public function getStatusColor() {
        switch ($this->status) {
            case 'GREEN': return '#27ae60';
            case 'YELLOW': return '#f39c12';
            case 'RED': return '#e74c3c';
            default: return '#95a5a6';
        }
    }

    /**
     * Give a status description based on the status.
     */
    public function validate() {
        $errors = [];

        if (empty($this->ip)) {
            $errors[] = 'IP address is required';
        } elseif (!filter_var($this->ip, FILTER_VALIDATE_IP)) {
            $errors[] = 'Invalid IP address format';
        }

        if (empty($this->datetime)) {
            $errors[] = 'Datetime is required';
        }

        if (empty($this->status)) {
            $errors[] = 'Status is required';
        } elseif (!in_array($this->status, ['GREEN', 'YELLOW', 'RED'])) {
            $errors[] = 'Status must be GREEN, YELLOW, or RED';
        }

        if ($this->mailsSend < 0) {
            $errors[] = 'Mails sent cannot be negative';
        }

        if ($this->mailsDelivered < 0) {
            $errors[] = 'Mails delivered cannot be negative';
        }

        if ($this->mailsDelivered > $this->mailsSend) {
            $errors[] = 'Mails delivered cannot exceed mails sent';
        }

        return $errors;
    }

    /**
     * Check if the current instance is valid.
     */
    public function isValid() {
        return empty($this->validate());
    }
}