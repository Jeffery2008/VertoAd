<?php require_once 'layout.php'; ?>

<div class="container mt-4">
    <h2>Create New Advertisement</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="/advertiser/create-ad" method="post">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        
        <div class="mb-3">
            <label for="position_id" class="form-label">Ad Position</label>
            <select class="form-control" id="position_id" name="position_id" required>
                <option value="">Select Ad Position</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?php echo htmlspecialchars($position['id']); ?>">
                        <?php echo htmlspecialchars($position['name']); ?> 
                        (<?php echo htmlspecialchars($position['width']); ?>x<?php echo htmlspecialchars($position['height']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
        </div>
        
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date">
            <small class="form-text text-muted">Optional. Leave blank for indefinite.</small>
        </div>
        
        <div class="mb-3">
            <label for="budget" class="form-label">Budget</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="bid_amount" class="form-label">Bid Amount (per 1000 impressions)</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="bid_amount" name="bid_amount" min="0.1" step="0.01" required>
            </div>
        </div>

        <!-- Location Targeting Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Location Targeting</h5>
            </div>
            <div class="card-body">
                <p class="card-text">Target your ad to specific countries. Leave blank to target all locations.</p>
                
                <div id="location-container">
                    <div class="row mb-2">
                        <div class="col-md-10">
                            <select class="form-control location-select" name="locations[]">
                                <option value="">Select a country</option>
                                <?php foreach ($commonLocations as $code => $name): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger remove-location" disabled>Remove</button>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-location">
                    <i class="fas fa-plus"></i> Add Another Location
                </button>
            </div>
        </div>

        <!-- Device Targeting Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Device Targeting</h5>
            </div>
            <div class="card-body">
                <p class="card-text">Target your ad to specific device types. Leave unselected to target all devices.</p>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="Desktop" id="deviceDesktop" name="devices[]">
                    <label class="form-check-label" for="deviceDesktop">
                        Desktop
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="Mobile" id="deviceMobile" name="devices[]">
                    <label class="form-check-label" for="deviceMobile">
                        Mobile
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="Tablet" id="deviceTablet" name="devices[]">
                    <label class="form-check-label" for="deviceTablet">
                        Tablet
                    </label>
                </div>
            </div>
        </div>

        <!-- Time-based Targeting Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Time-based Targeting</h5>
            </div>
            <div class="card-body">
                <p class="card-text">Schedule your ad to run on specific days and times. Leave unselected to run at all times.</p>
                
                <!-- Days of Week Selection -->
                <div class="mb-3">
                    <label class="form-label">Days of Week</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Monday" id="dayMonday" name="days[]">
                                <label class="form-check-label" for="dayMonday">Monday</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Tuesday" id="dayTuesday" name="days[]">
                                <label class="form-check-label" for="dayTuesday">Tuesday</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Wednesday" id="dayWednesday" name="days[]">
                                <label class="form-check-label" for="dayWednesday">Wednesday</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Thursday" id="dayThursday" name="days[]">
                                <label class="form-check-label" for="dayThursday">Thursday</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Friday" id="dayFriday" name="days[]">
                                <label class="form-check-label" for="dayFriday">Friday</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Saturday" id="daySaturday" name="days[]">
                                <label class="form-check-label" for="daySaturday">Saturday</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Sunday" id="daySunday" name="days[]">
                                <label class="form-check-label" for="daySunday">Sunday</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Time Range Selection -->
                <div class="mb-3">
                    <label class="form-label">Time Range (24-hour format)</label>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="startTime" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="startTime" name="start_time">
                        </div>
                        <div class="col-md-6">
                            <label for="endTime" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="endTime" name="end_time">
                        </div>
                    </div>
                    <small class="form-text text-muted">Leave both fields empty to run the ad all day.</small>
                </div>
            </div>
        </div>

        <!-- Browser Targeting Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Browser Targeting</h5>
            </div>
            <div class="card-body">
                <p class="card-text">Target your ad to specific browsers. Leave unselected to target all browsers.</p>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Chrome" id="browserChrome" name="browsers[]">
                            <label class="form-check-label" for="browserChrome">
                                Chrome
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Firefox" id="browserFirefox" name="browsers[]">
                            <label class="form-check-label" for="browserFirefox">
                                Firefox
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Safari" id="browserSafari" name="browsers[]">
                            <label class="form-check-label" for="browserSafari">
                                Safari
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Internet Explorer" id="browserIE" name="browsers[]">
                            <label class="form-check-label" for="browserIE">
                                Internet Explorer
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Opera" id="browserOpera" name="browsers[]">
                            <label class="form-check-label" for="browserOpera">
                                Opera
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OS Targeting Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Operating System Targeting</h5>
            </div>
            <div class="card-body">
                <p class="card-text">Target your ad to specific operating systems. Leave unselected to target all operating systems.</p>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Windows" id="osWindows" name="os[]">
                            <label class="form-check-label" for="osWindows">
                                Windows
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Mac OS" id="osMacOS" name="os[]">
                            <label class="form-check-label" for="osMacOS">
                                Mac OS
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Linux" id="osLinux" name="os[]">
                            <label class="form-check-label" for="osLinux">
                                Linux
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Android" id="osAndroid" name="os[]">
                            <label class="form-check-label" for="osAndroid">
                                Android
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="iOS" id="osIOS" name="os[]">
                            <label class="form-check-label" for="osIOS">
                                iOS
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <p>After creating the ad, you'll be able to design its content using our canvas tool.</p>
        </div>
        
        <div class="mb-3">
            <button type="submit" class="btn btn-primary">Create Advertisement</button>
            <a href="/advertiser/ads" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    // Add date validation
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        // Set minimum start date to today
        const today = new Date().toISOString().split('T')[0];
        startDateInput.min = today;
        
        // Update end date minimum when start date changes
        startDateInput.addEventListener('change', function() {
            endDateInput.min = startDateInput.value;
            
            // If end date is earlier than start date, reset it
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = '';
            }
        });

        // Location targeting functionality
        const locationContainer = document.getElementById('location-container');
        const addLocationBtn = document.getElementById('add-location');
        
        // Add new location field
        addLocationBtn.addEventListener('click', function() {
            const locationRow = document.createElement('div');
            locationRow.className = 'row mb-2';
            locationRow.innerHTML = `
                <div class="col-md-10">
                    <select class="form-control location-select" name="locations[]">
                        <option value="">Select a country</option>
                        <?php foreach ($commonLocations as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger remove-location">Remove</button>
                </div>
            `;
            locationContainer.appendChild(locationRow);
            
            // Enable the first remove button if we have more than one location
            if (locationContainer.children.length > 1) {
                locationContainer.querySelector('.remove-location').disabled = false;
            }
        });
        
        // Remove location field
        locationContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-location')) {
                e.target.closest('.row').remove();
                
                // If only one location remains, disable its remove button
                if (locationContainer.children.length === 1) {
                    locationContainer.querySelector('.remove-location').disabled = true;
                }
            }
        });
    });
</script> 