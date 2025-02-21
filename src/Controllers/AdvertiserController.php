<?php

namespace App\Controllers;

use App\Services\KeyRedemptionService;
use App\Services\AccountService;
use App\Services\KeyGenerationService;

class AdvertiserController extends BaseController
{
    private $keyRedemptionService;
    private $accountService;

    public function __construct()
    {
        parent::__construct();

        // Initialize services
        $this->keyRedemptionService = new KeyRedemptionService(
            $this->logger,
            new KeyGenerationService($this->db, $this->logger),
            new AccountService($this->db, $this->logger),
            $this->db
        );
        $this->accountService = new AccountService($this->db, $this->logger);
    }

    /**
     * Display the key activation form page
     */
    public function showActivatePage()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login?redirect=' . urlencode('/advertiser/activate'));
            return;
        }

        $user = $this->auth->getUser();

        // Get recent activations for display
        $activations = $this->keyRedemptionService->getUserActivationHistory($user->getId(), 3);

        $this->render('advertiser/activate', [
            'user' => $user,
            'activations' => $activations
        ]);
    }

    /**
     * Display the activation success page
     */
    public function showActivationSuccess()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login');
            return;
        }

        // Validate amount parameter
        $amount = filter_input(INPUT_GET, 'amount', FILTER_VALIDATE_FLOAT);
        if ($amount === false) {
            // Redirect back to activation page if amount is invalid
            $this->redirect('/advertiser/activate');
            return;
        }

        $user = $this->auth->getUser();
        $balance = $this->accountService->getBalance($user->getId());

        $this->render('advertiser/activation-success', [
            'user' => $user,
            'amount' => $amount,
            'previousBalance' => $balance - $amount,
            'newBalance' => $balance
        ]);
    }
}
