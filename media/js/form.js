/**
 * Moloch Component Form JavaScript
 * Author: Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * Copyright (C) 2025. All rights reserved.
 * License: GNU General Public License version 2 or later
 */

'use strict';

// Global MolochForm object
window.MolochForm = window.MolochForm || {};

/**
 * Form functionality
 */
(function() {
    
    // Configuration from PHP
    const config = window.MolochFormConfig || {};
    const token = window.MolochToken || '';
    
    // State management
    let state = {
        map: null,
        marker: null,
        geocoder: null,
        uploadQueue: [],
        uploadedFiles: []
    };

    /**
     * Initialize form functionality
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        console.log('Initializing Moloch Form');
        
        // Initialize modules
        initMap();
        initFileUpload();
        initFormValidation();
        initUI();
        
        console.log('Moloch Form initialized successfully');
    }

    /**
     * Initialize map functionality
     */
    function initMap() {
        const mapContainer = document.getElementById('location-map');
        if (!mapContainer) return;

        const mapProvider = config.mapProvider || 'googlemaps';
        
        if (mapProvider === 'googlemaps' && window.google) {
            initGoogleMap(mapContainer);
        } else if (mapProvider === 'osm' && window.L) {
            initLeafletMap(mapContainer);
        } else {
            console.warn('Map provider not available:', mapProvider);
            showMapError();
        }
    }

    /**
     * Initialize Google Maps for form
     */
    function initGoogleMap(container) {
        try {
            // Determine initial position
            let initialLat = config.defaultLat || 20.6296;
            let initialLng = config.defaultLng || -87.0739;
            
            if (config.item && config.item.latitude && config.item.longitude) {
                initialLat = parseFloat(config.item.latitude);
                initialLng = parseFloat(config.item.longitude);
            }

            const mapOptions = {
                center: { lat: initialLat, lng: initialLng },
                zoom: config.defaultZoom || 15,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: false,
                zoomControl: true
            };

            state.map = new google.maps.Map(container, mapOptions);
            
            // Initialize geocoder
            state.geocoder = new google.maps.Geocoder();

            // Add marker
            state.marker = new google.maps.Marker({
                position: { lat: initialLat, lng: initialLng },
                map: state.map,
                draggable: true,
                title: config.texts.dragMarker || 'Drag to set location'
            });

            // Add marker event listeners
            state.marker.addListener('dragend', function() {
                const position = state.marker.getPosition();
                updateCoordinates(position.lat(), position.lng());
                reverseGeocode(position.lat(), position.lng());
            });

            // Add map click listener
            state.map.addListener('click', function(event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                
                state.marker.setPosition(event.latLng);
                updateCoordinates(lat, lng);
                reverseGeocode(lat, lng);
            });

            // Hide loading indicator
            hideMapLoading();
            
            console.log('Google Maps initialized for form');

        } catch (error) {
            console.error('Error initializing Google Maps:', error);
            showMapError();
        }
    }

    /**
     * Initialize Leaflet map for form
     */
    function initLeafletMap(container) {
        try {
            // Determine initial position
            let initialLat = config.defaultLat || 20.6296;
            let initialLng = config.defaultLng || -87.0739;
            
            if (config.item && config.item.latitude && config.item.longitude) {
                initialLat = parseFloat(config.item.latitude);
                initialLng = parseFloat(config.item.longitude);
            }

            state.map = L.map(container, {
                center: [initialLat, initialLng],
                zoom: config.defaultZoom || 15
            });

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(state.map);

            // Add marker
            state.marker = L.marker([initialLat, initialLng], {
                draggable: true
            }).addTo(state.map);

            // Add marker event listeners
            state.marker.on('dragend', function(event) {
                const position = event.target.getLatLng();
                updateCoordinates(position.lat, position.lng);
                reverseGeocodeLeaflet(position.lat, position.lng);
            });

            // Add map click listener
            state.map.on('click', function(event) {
                const lat = event.latlng.lat;
                const lng = event.latlng.lng;
                
                state.marker.setLatLng([lat, lng]);
                updateCoordinates(lat, lng);
                reverseGeocodeLeaflet(lat, lng);
            });

            // Hide loading indicator
            hideMapLoading();
            
            console.log('Leaflet map initialized for form');

        } catch (error) {
            console.error('Error initializing Leaflet map:', error);
            showMapError();
        }
    }

    /**
     * Update coordinate inputs
     */
    function updateCoordinates(lat, lng) {
        const latInput = document.getElementById('jform_latitude');
        const lngInput = document.getElementById('jform_longitude');
        
        if (latInput) latInput.value = lat.toFixed(8);
        if (lngInput) lngInput.value = lng.toFixed(8);
    }

    /**
     * Reverse geocode with Google Maps
     */
    function reverseGeocode(lat, lng) {
        if (!state.geocoder) return;

        state.geocoder.geocode(
            { location: { lat: lat, lng: lng } },
            function(results, status) {
                if (status === 'OK' && results[0]) {
                    const addressInput = document.getElementById('jform_address');
                    if (addressInput && !addressInput.value) {
                        addressInput.value = results[0].formatted_address;
                    }
                }
            }
        );
    }

    /**
     * Reverse geocode with Leaflet (Nominatim)
     */
    function reverseGeocodeLeaflet(lat, lng) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    const addressInput = document.getElementById('jform_address');
                    if (addressInput && !addressInput.value) {
                        addressInput.value = data.display_name;
                    }
                }
            })
            .catch(error => {
                console.warn('Reverse geocoding error:', error);
            });
    }

    /**
     * Initialize file upload functionality
     */
    function initFileUpload() {
        const dropZone = document.getElementById('file-drop-zone');
        const fileInput = document.getElementById('file-input');
        const selectBtn = document.getElementById('select-files-btn');

        if (!dropZone || !fileInput || !selectBtn) return;

        // Drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        dropZone.addEventListener('drop', handleDrop, false);

        // File input change
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });

        // Select files button
        selectBtn.addEventListener('click', function() {
            fileInput.click();
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight() {
            dropZone.classList.add('drag-over');
        }

        function unhighlight() {
            dropZone.classList.remove('drag-over');
        }

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            handleFiles(files);
        }
    }

    /**
     * Handle selected files
     */
    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (validateFile(file)) {
                uploadFile(file);
            }
        });
    }

    /**
     * Validate file
     */
    function validateFile(file) {
        // Check file size
        const maxSize = config.maxFileSize || 1073741824; // 1GB default
        if (file.size > maxSize) {
            showNotification(
                `${file.name}: ${config.texts.fileTooLarge || 'File too large'}`,
                'error'
            );
            return false;
        }

        // Check file type
        const allowedTypes = config.allowedFileTypes || [];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (allowedTypes.length > 0 && !allowedTypes.includes(fileExtension)) {
            showNotification(
                `${file.name}: ${config.texts.fileTypeNotAllowed || 'File type not allowed'}`,
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Upload file
     */
    function uploadFile(file) {
        const progressContainer = document.getElementById('upload-progress-container');
        const progressList = document.getElementById('upload-progress-list');
        
        if (!progressContainer || !progressList) return;

        // Show progress container
        progressContainer.style.display = 'block';

        // Create progress element
        const progressId = 'progress-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        const progressElement = createProgressElement(file.name, progressId);
        progressList.appendChild(progressElement);

        // Create form data
        const formData = new FormData();
        formData.append('files[]', file);
        formData.append('task', 'upload');
        formData.append('issue_id', config.item ? config.item.id : 0);
        formData.append(token, '1');

        // Create XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();

        // Progress event
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                updateProgressBar(progressId, percentComplete);
            }
        });

        // Load event
        xhr.addEventListener('load', function() {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    showNotification(
                        `${file.name} uploaded successfully`,
                        'success'
                    );
                    
                    // Add to uploaded files
                    if (response.files && response.files.length > 0) {
                        state.uploadedFiles = state.uploadedFiles.concat(response.files);
                        updateFileDisplay();
                    }
                    
                    // Remove progress element with delay
                    setTimeout(() => {
                        progressElement.remove();
                        
                        // Hide progress container if no more uploads
                        if (progressList.children.length === 0) {
                            progressContainer.style.display = 'none';
                        }
                    }, 1000);
                    
                } else {
                    throw new Error(response.message || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showNotification(
                    `${file.name}: ${error.message || config.texts.uploadError || 'Upload failed'}`,
                    'error'
                );
                
                progressElement.remove();
                
                if (progressList.children.length === 0) {
                    progressContainer.style.display = 'none';
                }
            }
        });

        // Error event
        xhr.addEventListener('error', function() {
            showNotification(
                `${file.name}: Network error`,
                'error'
            );
            
            progressElement.remove();
            
            if (progressList.children.length === 0) {
                progressContainer.style.display = 'none';
            }
        });

        // Send request
        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    }

    /**
     * Create progress element
     */
    function createProgressElement(filename, progressId) {
        const div = document.createElement('div');
        div.className = 'upload-progress-item mb-2';
        div.id = progressId;
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="filename">${filename}</small>
                <small class="percentage">0%</small>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                     style="width: 0%"></div>
            </div>
        `;
        return div;
    }

    /**
     * Update progress bar
     */
    function updateProgressBar(progressId, percentage) {
        const progressElement = document.getElementById(progressId);
        if (!progressElement) return;

        const percentageText = progressElement.querySelector('.percentage');
        const progressBar = progressElement.querySelector('.progress-bar');

        if (percentageText) {
            percentageText.textContent = Math.round(percentage) + '%';
        }

        if (progressBar) {
            progressBar.style.width = percentage + '%';
            
            if (percentage >= 100) {
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
            }
        }
    }

    /**
     * Update file display
     */
    function updateFileDisplay() {
        // This function would update the UI to show newly uploaded files
        // Implementation depends on specific requirements
        console.log('Uploaded files:', state.uploadedFiles);
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const form = document.getElementById('adminForm');
        if (!form) return;

        // Custom validation for coordinates
        const latInput = document.getElementById('jform_latitude');
        const lngInput = document.getElementById('jform_longitude');

        if (latInput) {
            latInput.addEventListener('change', function() {
                const lat = parseFloat(this.value);
                if (isNaN(lat) || lat < -90 || lat > 90) {
                    this.setCustomValidity('Invalid latitude (-90 to 90)');
                } else {
                    this.setCustomValidity('');
                    // Update map if valid
                    if (state.marker && !isNaN(parseFloat(lngInput.value))) {
                        updateMapPosition(lat, parseFloat(lngInput.value));
                    }
                }
            });
        }

        if (lngInput) {
            lngInput.addEventListener('change', function() {
                const lng = parseFloat(this.value);
                if (isNaN(lng) || lng < -180 || lng > 180) {
                    this.setCustomValidity('Invalid longitude (-180 to 180)');
                } else {
                    this.setCustomValidity('');
                    // Update map if valid
                    if (state.marker && !isNaN(parseFloat(latInput.value))) {
                        updateMapPosition(parseFloat(latInput.value), lng);
                    }
                }
            });
        }
    }

    /**
     * Update map position
     */
    function updateMapPosition(lat, lng) {
        if (!state.map || !state.marker) return;

        if (config.mapProvider === 'googlemaps') {
            const position = new google.maps.LatLng(lat, lng);
            state.marker.setPosition(position);
            state.map.setCenter(position);
        } else {
            state.marker.setLatLng([lat, lng]);
            state.map.setView([lat, lng]);
        }
    }

    /**
     * Initialize UI functionality
     */
    function initUI() {
        // Search address button
        const searchBtn = document.getElementById('search-address-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', searchAddress);
        }

        // Detect location button
        const detectBtn = document.getElementById('detect-location-btn');
        if (detectBtn) {
            detectBtn.addEventListener('click', detectLocation);
        }

        // Delete attachment buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-attachment-btn')) {
                e.preventDefault();
                const button = e.target.closest('.delete-attachment-btn');
                const attachmentId = button.getAttribute('data-attachment-id');
                if (attachmentId) {
                    deleteAttachment(attachmentId, button);
                }
            }
        });
    }

    /**
     * Search address
     */
    function searchAddress() {
        const addressInput = document.getElementById('jform_address');
        if (!addressInput || !addressInput.value.trim()) {
            showNotification('Please enter an address to search', 'warning');
            return;
        }

        const address = addressInput.value.trim();
        const searchBtn = document.getElementById('search-address-btn');
        
        if (searchBtn) {
            searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            searchBtn.disabled = true;
        }

        if (config.mapProvider === 'googlemaps' && state.geocoder) {
            // Google Maps geocoding
            state.geocoder.geocode({ address: address }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    const lat = location.lat();
                    const lng = location.lng();
                    
                    state.marker.setPosition(location);
                    state.map.setCenter(location);
                    state.map.setZoom(16);
                    
                    updateCoordinates(lat, lng);
                    showNotification('Location found successfully', 'success');
                } else {
                    showNotification('Address not found', 'error');
                }
            });
        } else {
            // Nominatim geocoding for OSM
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        state.marker.setLatLng([lat, lng]);
                        state.map.setView([lat, lng], 16);
                        
                        updateCoordinates(lat, lng);
                        showNotification('Location found successfully', 'success');
                    } else {
                        showNotification('Address not found', 'error');
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    showNotification('Error searching address', 'error');
                });
        }

        // Reset button
        if (searchBtn) {
            setTimeout(() => {
                searchBtn.innerHTML = '<i class="fas fa-search"></i>';
                searchBtn.disabled = false;
            }, 1000);
        }
    }

    /**
     * Detect user location
     */
    function detectLocation() {
        if (!navigator.geolocation) {
            showNotification('Geolocation is not supported by this browser', 'error');
            return;
        }

        const detectBtn = document.getElementById('detect-location-btn');
        
        if (detectBtn) {
            detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            detectBtn.disabled = true;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                updateMapPosition(lat, lng);
                updateCoordinates(lat, lng);
                
                // Reverse geocode
                if (config.mapProvider === 'googlemaps') {
                    reverseGeocode(lat, lng);
                } else {
                    reverseGeocodeLeaflet(lat, lng);
                }
                
                showNotification('Location detected successfully', 'success');
            },
            function(error) {
                let message = 'Unable to retrieve your location';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Location access denied by user';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Location information unavailable';
                        break;
                    case error.TIMEOUT:
                        message = 'Location request timed out';
                        break;
                }
                showNotification(message, 'error');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );

        // Reset button
        if (detectBtn) {
            setTimeout(() => {
                detectBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                detectBtn.disabled = false;
            }, 2000);
        }
    }

    /**
     * Delete attachment
     */
    function deleteAttachment(attachmentId, button) {
        if (!confirm('Are you sure you want to delete this attachment?')) {
            return;
        }

        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        const formData = new FormData();
        formData.append('task', 'deleteAttachment');
        formData.append('attachment_id', attachmentId);
        formData.append(token, '1');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove attachment element
                const attachmentCard = button.closest('.attachment-item').parentElement;
                attachmentCard.remove();
                
                showNotification('Attachment deleted successfully', 'success');
            } else {
                showNotification(data.message || 'Error deleting attachment', 'error');
                button.innerHTML = '<i class="fas fa-trash"></i>';
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showNotification('Network error', 'error');
            button.innerHTML = '<i class="fas fa-trash"></i>';
            button.disabled = false;
        });
    }

    /**
     * Utility functions
     */

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        if (window.Moloch && window.Moloch.showNotification) {
            window.Moloch.showNotification(message, type);
        } else {
            // Fallback notification
            alert(message);
        }
    }

    /**
     * Hide map loading indicator
     */
    function hideMapLoading() {
        const loadingElement = document.querySelector('.map-loading');
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
    }

    /**
     * Show map error
     */
    function showMapError() {
        const mapContainer = document.getElementById('location-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-center bg-light">
                    <div>
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h6>Map not available</h6>
                        <p class="text-muted small">Unable to load map. Please check your configuration.</p>
                    </div>
                </div>
            `;
        }
    }

    // Expose public API
    window.MolochForm.init = init;
    window.MolochForm.state = state;

})();

// Global callback for Google Maps
window.initFormMap = function() {
    if (window.MolochForm && window.MolochForm.init) {
        window.MolochForm.init();
    }
};