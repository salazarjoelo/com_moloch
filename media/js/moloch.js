/**
 * Moloch Component Main JavaScript
 * Author: Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * Copyright (C) 2025. All rights reserved.
 * License: GNU General Public License version 2 or later
 */

'use strict';

// Global Moloch object
window.Moloch = window.Moloch || {};

/**
 * Main Moloch functionality
 */
(function() {
    
    // Configuration from PHP
    const config = window.MolochConfig || {};
    const token = window.MolochToken || '';
    
    // State management
    let state = {
        map: null,
        markers: [],
        infoWindow: null,
        userLocation: null,
        filters: {
            categories: [],
            steps: [],
            search: ''
        }
    };

    /**
     * Initialize Moloch functionality
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        console.log('Initializing Moloch Component');
        
        // Initialize modules
        initMap();
        initVoting();
        initFilters();
        initUI();
        initFileUpload();
        
        console.log('Moloch Component initialized successfully');
    }

    /**
     * Initialize map functionality
     */
    function initMap() {
        const mapContainer = document.getElementById('moloch-map');
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
     * Initialize Google Maps
     */
    function initGoogleMap(container) {
        try {
            const mapOptions = {
                center: {
                    lat: config.defaultLat || 20.6296,
                    lng: config.defaultLng || -87.0739
                },
                zoom: config.defaultZoom || 12,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                styles: getGoogleMapStyles(),
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_CENTER
                }
            };

            state.map = new google.maps.Map(container, mapOptions);
            
            // Initialize info window
            state.infoWindow = new google.maps.InfoWindow({
                maxWidth: 300
            });

            // Add markers
            addGoogleMarkers();
            
            // Add map controls
            addGoogleMapControls();
            
            // Hide loading indicator
            hideMapLoading();
            
            console.log('Google Maps initialized');

        } catch (error) {
            console.error('Error initializing Google Maps:', error);
            showMapError();
        }
    }

    /**
     * Initialize Leaflet (OpenStreetMap)
     */
    function initLeafletMap(container) {
        try {
            state.map = L.map(container, {
                center: [config.defaultLat || 20.6296, config.defaultLng || -87.0739],
                zoom: config.defaultZoom || 12,
                zoomControl: false
            });

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(state.map);

            // Add zoom control
            L.control.zoom({
                position: 'bottomright'
            }).addTo(state.map);

            // Add markers
            addLeafletMarkers();
            
            // Hide loading indicator
            hideMapLoading();
            
            console.log('Leaflet map initialized');

        } catch (error) {
            console.error('Error initializing Leaflet map:', error);
            showMapError();
        }
    }

    /**
     * Add Google Maps markers
     */
    function addGoogleMarkers() {
        if (!config.issues || !Array.isArray(config.issues)) return;

        config.issues.forEach(issue => {
            const marker = new google.maps.Marker({
                position: {
                    lat: parseFloat(issue.latitude),
                    lng: parseFloat(issue.longitude)
                },
                map: state.map,
                title: issue.title,
                icon: createGoogleMarkerIcon(issue.category.color)
            });

            // Add click event
            marker.addListener('click', () => {
                showIssueInfo(issue, marker);
            });

            state.markers.push({
                marker: marker,
                issue: issue
            });
        });

        // Fit map to show all markers
        if (state.markers.length > 0) {
            fitMapToMarkers();
        }
    }

    /**
     * Add Leaflet markers
     */
    function addLeafletMarkers() {
        if (!config.issues || !Array.isArray(config.issues)) return;

        config.issues.forEach(issue => {
            const marker = L.marker([
                parseFloat(issue.latitude),
                parseFloat(issue.longitude)
            ], {
                icon: createLeafletMarkerIcon(issue.category.color)
            }).addTo(state.map);

            // Add click event
            marker.on('click', () => {
                showIssueInfo(issue, marker);
            });

            state.markers.push({
                marker: marker,
                issue: issue
            });
        });

        // Fit map to show all markers
        if (state.markers.length > 0) {
            fitMapToMarkers();
        }
    }

    /**
     * Create Google Maps marker icon
     */
    function createGoogleMarkerIcon(color) {
        return {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: color,
            fillOpacity: 0.8,
            strokeColor: '#ffffff',
            strokeWeight: 2,
            scale: 8
        };
    }

    /**
     * Create Leaflet marker icon
     */
    function createLeafletMarkerIcon(color) {
        return L.divIcon({
            html: `<div class="custom-marker" style="background-color: ${color}"></div>`,
            className: 'custom-marker-container',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });
    }

    /**
     * Show issue information
     */
    function showIssueInfo(issue, marker) {
        const template = document.getElementById('issue-info-template');
        if (!template) return;

        const content = template.cloneNode(true);
        content.style.display = 'block';
        content.id = '';

        // Populate content
        content.querySelector('.issue-popup-title').textContent = issue.title;
        content.querySelector('.issue-popup-description').textContent = issue.description;
        content.querySelector('.issue-popup-address').innerHTML = 
            `<i class="fas fa-map-marker-alt"></i> ${issue.address || ''}`;
        content.querySelector('.issue-popup-date').innerHTML = 
            `<i class="fas fa-calendar"></i> ${issue.created}`;
        
        // Add badges
        const badgesContainer = content.querySelector('.issue-popup-badges');
        badgesContainer.innerHTML = `
            <span class="badge badge-sm" style="background-color: ${issue.category.color}">
                ${issue.category.title}
            </span>
            <span class="badge badge-sm ml-1" style="background-color: ${issue.step.color}">
                ${issue.step.title}
            </span>
        `;

        // Add votes
        content.querySelector('.votes-up').textContent = issue.votes.up;
        content.querySelector('.votes-down').textContent = issue.votes.down;
        
        // Add link
        content.querySelector('.issue-popup-link').href = issue.url;

        // Show popup
        if (config.mapProvider === 'googlemaps') {
            state.infoWindow.setContent(content.innerHTML);
            state.infoWindow.open(state.map, marker);
        } else {
            marker.bindPopup(content.innerHTML).openPopup();
        }
    }

    /**
     * Initialize voting functionality
     */
    function initVoting() {
        document.addEventListener('click', function(e) {
            const voteBtn = e.target.closest('.vote-btn');
            if (!voteBtn) return;

            e.preventDefault();
            
            const issueId = voteBtn.getAttribute('data-issue-id');
            const voteValue = parseInt(voteBtn.getAttribute('data-vote'));
            
            if (!issueId || !voteValue) return;
            
            vote(issueId, voteValue, voteBtn);
        });
    }

    /**
     * Submit vote
     */
    function vote(issueId, voteValue, button) {
        // Prevent double-clicking
        if (button.disabled) return;
        button.disabled = true;
        
        // Add loading state
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const formData = new FormData();
        formData.append('task', 'vote');
        formData.append('id', issueId);
        formData.append('vote', voteValue);
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
                // Update vote counts
                updateVoteDisplay(issueId, data.votes);
                showNotification(data.message || 'Vote recorded successfully', 'success');
            } else {
                showNotification(data.message || 'Error recording vote', 'error');
            }
        })
        .catch(error => {
            console.error('Voting error:', error);
            showNotification('Network error. Please try again.', 'error');
        })
        .finally(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
        });
    }

    /**
     * Update vote display
     */
    function updateVoteDisplay(issueId, votes) {
        const issueElement = document.querySelector(`[data-issue-id="${issueId}"]`);
        if (!issueElement) return;

        const upButton = issueElement.querySelector('[data-vote="1"] .vote-count');
        const downButton = issueElement.querySelector('[data-vote="-1"] .vote-count');

        if (upButton) {
            upButton.textContent = votes.up;
            upButton.closest('.vote-btn').classList.add('counting');
        }
        if (downButton) {
            downButton.textContent = votes.down;
            downButton.closest('.vote-btn').classList.add('counting');
        }

        // Remove animation class after animation
        setTimeout(() => {
            issueElement.querySelectorAll('.counting').forEach(el => {
                el.classList.remove('counting');
            });
        }, 500);
    }

    /**
     * Initialize filters
     */
    function initFilters() {
        // Category filters
        document.querySelectorAll('.category-filter').forEach(checkbox => {
            checkbox.addEventListener('change', updateMapFilters);
        });

        // Step filters
        document.querySelectorAll('.step-filter').forEach(checkbox => {
            checkbox.addEventListener('change', updateMapFilters);
        });

        // Initialize filter state
        updateMapFilters();
    }

    /**
     * Update map filters
     */
    function updateMapFilters() {
        const categoryFilters = Array.from(document.querySelectorAll('.category-filter:checked'))
            .map(cb => parseInt(cb.getAttribute('data-category-id')));
        
        const stepFilters = Array.from(document.querySelectorAll('.step-filter:checked'))
            .map(cb => parseInt(cb.getAttribute('data-step-id')));

        state.filters.categories = categoryFilters;
        state.filters.steps = stepFilters;

        // Filter markers
        let visibleCount = 0;
        state.markers.forEach(markerData => {
            const issue = markerData.issue;
            const shouldShow = 
                (categoryFilters.length === 0 || categoryFilters.includes(issue.category.id)) &&
                (stepFilters.length === 0 || stepFilters.includes(issue.step.id));

            if (config.mapProvider === 'googlemaps') {
                markerData.marker.setVisible(shouldShow);
            } else {
                if (shouldShow) {
                    markerData.marker.addTo(state.map);
                } else {
                    state.map.removeLayer(markerData.marker);
                }
            }

            if (shouldShow) visibleCount++;
        });

        // Update visible count
        const countElement = document.getElementById('visible-markers-count');
        if (countElement) {
            countElement.textContent = visibleCount;
        }
    }

    /**
     * Initialize UI functionality
     */
    function initUI() {
        // Map controls
        initMapControls();
        
        // Fullscreen toggle
        initFullscreen();
        
        // Form enhancements
        initFormEnhancements();
    }

    /**
     * Initialize map controls
     */
    function initMapControls() {
        // Map type controls (Google Maps only)
        document.querySelectorAll('[data-map-type]').forEach(button => {
            button.addEventListener('click', function() {
                if (config.mapProvider !== 'googlemaps' || !state.map) return;
                
                const mapType = this.getAttribute('data-map-type');
                state.map.setMapTypeId(mapType);
                
                // Update active state
                document.querySelectorAll('[data-map-type]').forEach(b => 
                    b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Locate me button
        const locateBtn = document.getElementById('map-locate-me');
        if (locateBtn) {
            locateBtn.addEventListener('click', locateUser);
        }

        // Reset view button
        const resetBtn = document.getElementById('map-reset-view');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetMapView);
        }
    }

    /**
     * Locate user on map
     */
    function locateUser() {
        if (!navigator.geolocation) {
            showNotification('Geolocation is not supported by this browser', 'error');
            return;
        }

        const locateBtn = document.getElementById('map-locate-me');
        if (locateBtn) {
            locateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            locateBtn.disabled = true;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                state.userLocation = { lat, lng };

                if (config.mapProvider === 'googlemaps') {
                    state.map.setCenter({ lat, lng });
                    state.map.setZoom(15);
                    
                    // Add user marker if not exists
                    if (!state.userMarker) {
                        state.userMarker = new google.maps.Marker({
                            position: { lat, lng },
                            map: state.map,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                fillColor: '#4285F4',
                                fillOpacity: 1,
                                strokeColor: '#ffffff',
                                strokeWeight: 3,
                                scale: 10
                            },
                            title: 'Your location'
                        });
                    }
                } else {
                    state.map.setView([lat, lng], 15);
                    
                    if (!state.userMarker) {
                        state.userMarker = L.marker([lat, lng], {
                            icon: L.divIcon({
                                html: '<div class="user-location-marker"></div>',
                                className: 'user-location-container',
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            })
                        }).addTo(state.map);
                    }
                }

                showNotification('Location found successfully', 'success');
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
        ).finally(() => {
            if (locateBtn) {
                locateBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                locateBtn.disabled = false;
            }
        });
    }

    /**
     * Reset map view
     */
    function resetMapView() {
        if (!state.map) return;

        const defaultLat = config.defaultLat || 20.6296;
        const defaultLng = config.defaultLng || -87.0739;
        const defaultZoom = config.defaultZoom || 12;

        if (config.mapProvider === 'googlemaps') {
            state.map.setCenter({ lat: defaultLat, lng: defaultLng });
            state.map.setZoom(defaultZoom);
        } else {
            state.map.setView([defaultLat, defaultLng], defaultZoom);
        }

        // Reset all filters
        document.querySelectorAll('.category-filter, .step-filter').forEach(cb => {
            cb.checked = true;
        });
        
        updateMapFilters();
        showNotification('Map view reset', 'info');
    }

    /**
     * Initialize fullscreen functionality
     */
    function initFullscreen() {
        const fullscreenBtn = document.getElementById('map-fullscreen-toggle');
        if (!fullscreenBtn) return;

        fullscreenBtn.addEventListener('click', function() {
            const mapWrapper = document.querySelector('.moloch-map-wrapper');
            if (!mapWrapper) return;

            if (mapWrapper.classList.contains('map-fullscreen')) {
                exitFullscreen(mapWrapper);
            } else {
                enterFullscreen(mapWrapper);
            }
        });

        // Handle ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const fullscreenMap = document.querySelector('.map-fullscreen');
                if (fullscreenMap) {
                    exitFullscreen(fullscreenMap);
                }
            }
        });
    }

    /**
     * Enter fullscreen mode
     */
    function enterFullscreen(element) {
        element.classList.add('map-fullscreen');
        
        const button = document.getElementById('map-fullscreen-toggle');
        if (button) {
            button.innerHTML = '<i class="fas fa-compress"></i>';
            button.title = 'Exit fullscreen';
        }

        // Trigger map resize
        setTimeout(() => {
            if (state.map) {
                if (config.mapProvider === 'googlemaps') {
                    google.maps.event.trigger(state.map, 'resize');
                } else {
                    state.map.invalidateSize();
                }
            }
        }, 300);
    }

    /**
     * Exit fullscreen mode
     */
    function exitFullscreen(element) {
        element.classList.remove('map-fullscreen');
        
        const button = document.getElementById('map-fullscreen-toggle');
        if (button) {
            button.innerHTML = '<i class="fas fa-expand"></i>';
            button.title = 'Fullscreen';
        }

        // Trigger map resize
        setTimeout(() => {
            if (state.map) {
                if (config.mapProvider === 'googlemaps') {
                    google.maps.event.trigger(state.map, 'resize');
                } else {
                    state.map.invalidateSize();
                }
            }
        }, 300);
    }

    /**
     * Initialize form enhancements
     */
    function initFormEnhancements() {
        // Auto-submit filters with debounce
        const searchInput = document.getElementById('filter_search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    // Auto-submit form after 500ms of no typing
                    const form = this.closest('form');
                    if (form) form.submit();
                }, 500);
            });
        }
    }

    /**
     * Initialize file upload functionality
     */
    function initFileUpload() {
        const dropZones = document.querySelectorAll('.file-drop-zone');
        
        dropZones.forEach(dropZone => {
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
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            e.currentTarget.classList.add('drag-over');
        }

        function unhighlight(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            handleFiles(files, e.currentTarget);
        }
    }

    /**
     * Handle file uploads
     */
    function handleFiles(files, dropZone) {
        Array.from(files).forEach(file => {
            uploadFile(file, dropZone);
        });
    }

    /**
     * Upload individual file
     */
    function uploadFile(file, dropZone) {
        const formData = new FormData();
        formData.append('files[]', file);
        formData.append('task', 'upload');
        formData.append(token, '1');

        // Show upload progress
        const progressElement = createProgressElement(file.name);
        dropZone.appendChild(progressElement);

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
                showNotification(`File "${file.name}" uploaded successfully`, 'success');
                // Update UI with new file
                updateFileDisplay(data.files);
            } else {
                showNotification(data.message || `Error uploading "${file.name}"`, 'error');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            showNotification(`Network error uploading "${file.name}"`, 'error');
        })
        .finally(() => {
            progressElement.remove();
        });
    }

    /**
     * Create progress element for file upload
     */
    function createProgressElement(filename) {
        const div = document.createElement('div');
        div.className = 'upload-progress mb-2';
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">${filename}</small>
                <small class="text-muted">Uploading...</small>
            </div>
            <div class="progress" style="height: 4px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     style="width: 100%"></div>
            </div>
        `;
        return div;
    }

    /**
     * Update file display after upload
     */
    function updateFileDisplay(files) {
        // Implementation depends on specific form structure
        console.log('Files uploaded:', files);
    }

    /**
     * Utility functions
     */

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.moloch-notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} moloch-notification position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${getNotificationIcon(type)} mr-2"></i>
                <span>${message}</span>
                <button type="button" class="close ml-auto" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);

        // Handle close button
        notification.querySelector('.close').addEventListener('click', () => {
            notification.remove();
        });
    }

    /**
     * Get notification icon
     */
    function getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
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
        const mapContainer = document.getElementById('moloch-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100 text-center">
                    <div>
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Map not available</h5>
                        <p class="text-muted">Unable to load map. Please check your configuration.</p>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Fit map to show all markers
     */
    function fitMapToMarkers() {
        if (state.markers.length === 0) return;

        if (config.mapProvider === 'googlemaps') {
            const bounds = new google.maps.LatLngBounds();
            state.markers.forEach(markerData => {
                bounds.extend(markerData.marker.getPosition());
            });
            state.map.fitBounds(bounds);
        } else {
            const group = new L.featureGroup(state.markers.map(m => m.marker));
            state.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    /**
     * Get Google Map styles
     */
    function getGoogleMapStyles() {
        return [
            {
                featureType: 'water',
                elementType: 'geometry',
                stylers: [{ color: '#e9e9e9' }, { lightness: 17 }]
            },
            {
                featureType: 'landscape',
                elementType: 'geometry',
                stylers: [{ color: '#f5f5f5' }, { lightness: 20 }]
            },
            {
                featureType: 'road.highway',
                elementType: 'geometry.fill',
                stylers: [{ color: '#ffffff' }, { lightness: 17 }]
            },
            {
                featureType: 'road.highway',
                elementType: 'geometry.stroke',
                stylers: [{ color: '#ffffff' }, { lightness: 29 }, { weight: 0.2 }]
            },
            {
                featureType: 'road.arterial',
                elementType: 'geometry',
                stylers: [{ color: '#ffffff' }, { lightness: 18 }]
            },
            {
                featureType: 'road.local',
                elementType: 'geometry',
                stylers: [{ color: '#ffffff' }, { lightness: 16 }]
            },
            {
                featureType: 'poi',
                elementType: 'geometry',
                stylers: [{ color: '#f5f5f5' }, { lightness: 21 }]
            }
        ];
    }

    // Expose public API
    window.Moloch.init = init;
    window.Moloch.showNotification = showNotification;
    window.Moloch.state = state;

    // Auto-initialize if not in admin
    if (!window.location.pathname.includes('/administrator/')) {
        init();
    }

})();

// Global callback for Google Maps
window.initMap = function() {
    if (window.Moloch && window.Moloch.init) {
        window.Moloch.init();
    }
};