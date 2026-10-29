<div class="col-md-12">
    <style>
        .offline-payment-container {
            background: linear-gradient(135deg, #fff5f0 0%, #ffe8e0 100%);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .offline-payment-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(237, 137, 54, 0.15);
        }
        
        .offline-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #fed7d7;
        }
        
        .offline-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 10px rgba(237, 137, 54, 0.3);
        }
        
        .offline-title {
            flex: 1;
        }
        
        .offline-title h4 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .offline-title p {
            margin: 0;
            font-size: 13px;
            color: #718096;
        }
        
        .offline-form {
            margin-top: 20px;
        }
        
        .offline-btn {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
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
            gap: 12px;
            box-shadow: 0 4px 15px rgba(237, 137, 54, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .offline-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(237, 137, 54, 0.4);
            background: linear-gradient(135deg, #dd6b20 0%, #ed8936 100%);
        }
        
        .offline-btn:active {
            transform: translateY(0);
        }
        
        .offline-btn i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        
        .offline-btn:hover i {
            transform: scale(1.1);
        }
        
        /* Ripple effect */
        .offline-btn:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .offline-btn:active:after {
            width: 300px;
            height: 300px;
        }
        
        .offline-info {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border-left: 4px solid #ed8936;
        }
        
        .help-block {
            margin: 10px 0;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .help-block i {
            color: #ed8936;
            margin-right: 8px;
        }
        
        .offline-instruction {
            background: #fef5e7;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .offline-instruction p {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #4a5568;
        }
        
        .offline-instruction ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .offline-instruction li {
            margin-bottom: 5px;
            font-size: 13px;
            color: #4a5568;
        }
        
        /* Loading state */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .offline-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .offline-btn.loading i.fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Notification styles */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
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
        
        .notification-toast.success {
            border-left: 4px solid #48bb78;
            color: #22543d;
        }
        
        .notification-toast.error {
            border-left: 4px solid #f56565;
            color: #742a2a;
        }
        
        .notification-toast i.success {
            color: #48bb78;
        }
        
        .notification-toast i.error {
            color: #f56565;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .offline-payment-container {
                padding: 20px;
            }
            
            .offline-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .offline-title h4 {
                font-size: 16px;
            }
            
            .offline-btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
    
    <div class="offline-payment-container">
        <div class="offline-header">
            <div class="offline-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <div class="offline-title">
                <h4>Offline Payment</h4>
                <p>Pay via bank transfer, cash deposit, or other offline methods</p>
            </div>
        </div>
        
        <form action="{{ action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'], [$package->id]) }}" 
              method="POST" 
              class="offline-form"
              onsubmit="return confirmOfflinePayment(event)">
            {{ csrf_field() }}
            <input type="hidden" name="gateway" value="{{ $k }}">
            
            <button type="submit" class="offline-btn" id="offlineSubmitBtn">
                <i class="fas fa-handshake"></i>
                <span>{{ $v }}</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
        <div class="offline-info">
            <div class="help-block">
                <i class="fas fa-info-circle"></i> 
                @lang('superadmin::lang.offline_pay_helptext')
            </div>
            
            @if(!empty($offline_payment_details))
                <div class="offline-instruction">
                    <i class="fas fa-university" style="color: #ed8936; margin-bottom: 10px; display: inline-block;"></i>
                    <strong>Payment Instructions:</strong>
                    {!! nl2br($offline_payment_details) !!}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function confirmOfflinePayment(event) {
        event.preventDefault();
        
        const button = document.getElementById('offlineSubmitBtn');
        const originalContent = button.innerHTML;
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner"></i> Processing...';
        button.classList.add('loading');
        button.disabled = true;
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to proceed with offline payment?\n\nYou will need to complete the payment according to the instructions provided.\nYour subscription will be activated after payment confirmation.')) {
            // Submit the form
            event.target.submit();
        } else {
            // Reset button
            button.innerHTML = originalContent;
            button.classList.remove('loading');
            button.disabled = false;
        }
        
        return false;
    }
    
    // Add notification function if needed
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification-toast ${type}`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} ${type}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
</script>