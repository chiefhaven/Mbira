<div class="col-md-12">
    @php
        $currency_code = $system_currency->code ?? 'MWK';
        $package_price = $package->price ?? 0;
    @endphp
    
    <style>
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .amount-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .amount-card h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            opacity: 0.9;
            letter-spacing: 1px;
        }
        
        .amount-card .amount {
            margin: 0;
            font-size: 36px;
            font-weight: bold;
        }
        
        .payment-methods {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .payment-method-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2d3748;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .payment-option {
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option input[type="radio"] {
            display: none;
        }
        
        .payment-option label {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 0;
        }
        
        .payment-option input[type="radio"]:checked + label {
            border-color: #667eea;
            background: linear-gradient(135deg, #f5f0ff 0%, #faf5ff 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .payment-option:hover label {
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .payment-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-right: 15px;
            font-size: 28px;
        }
        
        .airtel-icon {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
        }
        
        .tnm-icon {
            background: linear-gradient(135deg, #00a651 0%, #008040 100%);
            color: white;
        }
        
        .bank-icon {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-name {
            font-weight: 600;
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .payment-description {
            font-size: 12px;
            color: #718096;
        }
        
        .pay-button {
            position: relative;
            width: 100%;
            padding: 16px;
            margin-top: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .pay-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .pay-button:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .pay-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Loading Animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Modal Styles */
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .payment-modal-content {
            background: white;
            padding: 35px;
            border-radius: 20px;
            text-align: center;
            min-width: 320px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .payment-modal-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        .payment-modal h3 {
            margin: 0 0 10px 0;
            color: #2d3748;
        }
        
        .payment-modal p {
            color: #718096;
            margin: 0;
        }
        
        /* Notification Styles */
        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 380px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .custom-notification.success {
            background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%);
        }
        
        .custom-notification.error {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
        }
        
        .custom-notification.info {
            background: linear-gradient(135deg, #4299e1 0%, #2b6cb0 100%);
        }
        
        .custom-notification i {
            font-size: 20px;
        }
        
        .custom-notification-content {
            flex: 1;
        }
        
        .custom-notification-close {
            cursor: pointer;
            font-size: 18px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .custom-notification-close:hover {
            opacity: 1;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .payment-container {
                padding: 10px;
            }
            
            .amount-card .amount {
                font-size: 28px;
            }
            
            .payment-option label {
                padding: 12px 15px;
            }
            
            .payment-icon {
                width: 40px;
                height: 40px;
                font-size: 22px;
            }
            
            .payment-name {
                font-size: 14px;
            }
        }
    </style>
    
    <div class="payment-container">
        <!-- Amount Display Card -->
        <div class="amount-card">
            <h4>Total Amount to Pay</h4>
            <div class="amount">{{ $currency_code }} {{ number_format($package_price, 2) }}</div>
        </div>
        
        <div class="payment-methods">
            <div class="payment-method-title">
                <i class="fas fa-credit-card"></i> Select Payment Method
            </div>
            
            <form id="paymentForm">
                @csrf
                <input type="hidden" name="package_id" value="{{ $package->id ?? 0 }}">
                <input type="hidden" name="amount" value="{{ $package_price }}">
                <input type="hidden" name="currency" value="{{ $currency_code }}">
                <input type="hidden" name="gateway" value="onekhusa">
                <input type="hidden" name="business_id" value="{{ $user['business_id'] ?? session('user.business_id') }}">
                <input type="hidden" name="user_id" value="{{ $user['id'] ?? session('user.id') }}">
                
                <!-- Airtel Money Option -->
                <div class="payment-option">
                    <input type="radio" name="payment_method" id="airtel_money" value="airtel_money" required>
                    <label for="airtel_money">
                        <div class="payment-icon airtel-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="payment-info">
                            <div class="payment-name">Airtel Money</div>
                            <div class="payment-description">Pay using Airtel Money</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #cbd5e0;"></i>
                    </label>
                </div>
                
                <!-- TNM Mpamba Option -->
                <div class="payment-option">
                    <input type="radio" name="payment_method" id="tnm_mpamba" value="tnm_mpamba">
                    <label for="tnm_mpamba">
                        <div class="payment-icon tnm-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="payment-info">
                            <div class="payment-name">TNM Mpamba</div>
                            <div class="payment-description">Pay using TNM Mpamba</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #cbd5e0;"></i>
                    </label>
                </div>
                
                <!-- Bank Transfer Option -->
                <div class="payment-option">
                    <input type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                    <label for="bank_transfer">
                        <div class="payment-icon bank-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="payment-info">
                            <div class="payment-name">Bank Transfer</div>
                            <div class="payment-description">Pay via bank transfer</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #cbd5e0;"></i>
                    </label>
                </div>
                
                <button type="button" class="pay-button" onclick="processOneKhusaPayment()">
                    <i class="fas fa-lock"></i>
                    Proceed to Payment
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Payment Processing Modal -->
<div id="paymentModal" class="payment-modal">
    <div class="payment-modal-content">
        <div class="payment-modal-spinner"></div>
        <h3>Processing Payment</h3>
        <p>Please wait while we connect to the payment gateway...</p>
        <small style="display: block; margin-top: 15px; color: #a0aec0;">Do not close this window</small>
    </div>
</div>

<script>
    let paymentInProgress = false;
    
    async function processOneKhusaPayment() {
        if (paymentInProgress) {
            showNotification('Payment already in progress. Please wait...', 'info');
            return;
        }
        
        // Get selected payment method
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!selectedMethod) {
            showNotification('Please select a payment method', 'error');
            return;
        }
        
        const paymentMethod = selectedMethod.value;
        const packageId = document.querySelector('input[name="package_id"]').value;
        const amount = document.querySelector('input[name="amount"]').value;
        const currency = document.querySelector('input[name="currency"]').value;
        const gateway = document.querySelector('input[name="gateway"]').value;
        const businessId = document.querySelector('input[name="business_id"]').value;
        const userId = document.querySelector('input[name="user_id"]').value;
        
        // Generate unique reference
        const timestamp = Date.now();
        const randomStr = Math.random().toString(36).substring(2, 12);
        const merchantReference = `${paymentMethod.toUpperCase()}_${timestamp}_${randomStr}`;
        
        // Prepare payment data for OneKhusa
        const paymentData = {
            package_id: packageId,
            gateway: gateway,
            payment_method: paymentMethod,
            amount: amount,
            currency: currency,
            business_id: businessId,
            user_id: userId,
            merchant_reference: merchantReference,
            description: `Subscription payment via ${paymentMethod.replace('_', ' ').toUpperCase()}`
        };
        
        console.log('Initiating OneKhusa payment with method:', paymentMethod, paymentData);
        
        // Disable button and show loading
        const button = document.querySelector('.pay-button');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner"></i> Processing...';
        button.disabled = true;
        paymentInProgress = true;
        
        // Show modal
        const modal = document.getElementById('paymentModal');
        modal.style.display = 'flex';
        
        try {
            // Call initiateOneKhusaPayment endpoint
            const response = await fetch("{{ route('superadmin.onekhusa.initiate') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(paymentData)
            });
            
            const result = await response.json();
            console.log('OneKhusa initiation response:', result);
            
            // Hide modal
            modal.style.display = 'none';
            
            if (result.success && result.payment_url) {
                showNotification('Redirecting to payment gateway...', 'success');
                
                // Redirect to OneKhusa checkout page where user will select their mobile money or bank details
                setTimeout(() => {
                    window.location.href = result.payment_url;
                }, 1000);
            } else if (result.success && result.checkout_url) {
                showNotification('Redirecting to checkout...', 'success');
                setTimeout(() => {
                    window.location.href = result.checkout_url;
                }, 1000);
            } else {
                const errorMsg = result.message || 'Failed to initiate payment. Please try again.';
                showNotification(errorMsg, 'error');
                
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;
                paymentInProgress = false;
            }
        } catch (error) {
            modal.style.display = 'none';
            console.error('Payment initiation error:', error);
            showNotification('An error occurred: ' + (error.message || 'Unknown error'), 'error');
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            paymentInProgress = false;
        }
    }
    
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notif => notif.remove());
        
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="custom-notification-content">${message}</div>
            <div class="custom-notification-close">&times;</div>
        `;
        
        document.body.appendChild(notification);
        
        // Close button functionality
        const closeBtn = notification.querySelector('.custom-notification-close');
        closeBtn.addEventListener('click', () => notification.remove());
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification && notification.remove) notification.remove();
        }, 5000);
    }
    
    // Close modal when clicking outside
    document.getElementById('paymentModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            paymentInProgress = false;
            const button = document.querySelector('.pay-button');
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-lock"></i> Proceed to Payment';
            }
        }
    });
    
    // ESC key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('paymentModal');
            if (modal && modal.style.display === 'flex') {
                modal.style.display = 'none';
                paymentInProgress = false;
                const button = document.querySelector('.pay-button');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-lock"></i> Proceed to Payment';
                }
            }
        }
    });
    
    // Add hover effect to payment options
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('mouseenter', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (!radio.checked) {
                const label = this.querySelector('label');
                label.style.transform = 'translateX(5px)';
            }
        });
        
        option.addEventListener('mouseleave', function() {
            const label = this.querySelector('label');
            label.style.transform = 'translateX(0)';
        });
    });
</script>