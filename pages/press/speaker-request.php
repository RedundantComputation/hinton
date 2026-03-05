<?php
// Start session for CSRF token generation
session_start();

// Generate CSRF token for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speaker Request Form - Geoffrey Hinton</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bg-color: #ffefca;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-color);
        }

        .header {
            border-bottom: 1px solid #dee2e6;
            background: white !important;
        }

        main {
            background-color: var(--bg-color);
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--bs-primary);
        }

        .required-label::after {
            content: " *";
            color: #dc3545;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: #212529;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            display: block;
            margin-top: 0.25rem;
        }

        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input {
            border: 1px solid #dee2e6;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .form-check-input:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-check-label {
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .form-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }

        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            padding: 0.625rem 2rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #0a58ca;
            border-color: #0a58ca;
        }

        .btn-primary:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            padding: 0.625rem 2rem;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .privacy-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .privacy-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1rem;
        }

        .privacy-section p {
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .privacy-section p:last-child {
            margin-bottom: 0;
        }

        .form-card {
            background: white;
            border-radius: 0.5rem;
            padding: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .intro-text {
            font-size: 1.1rem;
            color: #495057;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        @media (max-width: 767px) {
            .form-card {
                padding: 1.5rem;
            }

            .form-buttons {
                flex-direction: column;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
            }

            .radio-group {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header py-3 bg-white sticky-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-0"><a href="../index.html" style="text-decoration: none; color: inherit;">Geoffrey E. Hinton</a></h1>
                    <p class="text-muted mb-0 small">Speaker Request Form</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="py-4">
        <div class="container">
            <div class="form-card">
                <h1 class="h2 mb-3">Speaker Request Form</h1>
                <p class="intro-text">We welcome invitations for Geoffrey Hinton to speak at your event. To submit your request, please complete the form below. Fields marked with an asterisk (<span style="color: #dc3545;">*</span>) are required.</p>

                <form id="speakerRequestForm" method="POST" action="process-speaker-request.php">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <!-- Honeypot field for spam prevention -->
                    <input type="text" name="website" style="display: none; position: absolute; left: -9999px;" autocomplete="off">
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h2 class="form-section-title">Contact Information</h2>
                        
                        <div class="mb-3">
                            <label for="yourName" class="form-label required-label">Your Name</label>
                            <input type="text" class="form-control" id="yourName" name="yourName" required>
                        </div>

                        <div class="mb-3">
                            <label for="emailAddress" class="form-label required-label">Email Address</label>
                            <input type="email" class="form-control" id="emailAddress" name="emailAddress" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required-label">Are you the point of contact?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="pointOfContact" id="pointYes" value="yes" required>
                                <label class="form-check-label" for="pointYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="pointOfContact" id="pointNo" value="no">
                                <label class="form-check-label" for="pointNo">No - Provide alternate point of contact</label>
                            </div>
                        </div>

                        <div class="mb-3" id="alternateContactDiv" style="display: none;">
                            <label for="alternateContact" class="form-label">Alternate Point of Contact Name</label>
                            <input type="text" class="form-control" id="alternateContact" name="alternateContact">
                        </div>

                        <div class="mb-3">
                            <label for="hostOrganization" class="form-label">Host Organization</label>
                            <input type="text" class="form-control" id="hostOrganization" name="hostOrganization">
                        </div>

                        <div class="mb-3">
                            <label for="organizationLocation" class="form-label">Organization Location</label>
                            <input type="text" class="form-control" id="organizationLocation" name="organizationLocation" placeholder="City, State/Province, Country">
                        </div>
                    </div>

                    <!-- Event Details Section -->
                    <div class="form-section">
                        <h2 class="form-section-title">Event Details</h2>

                        <div class="mb-3">
                            <label for="eventName" class="form-label">Event Name</label>
                            <input type="text" class="form-control" id="eventName" name="eventName">
                        </div>

                        <div class="mb-3">
                            <label for="eventPurpose" class="form-label required-label">Event Purpose</label>
                            <textarea class="form-control" id="eventPurpose" name="eventPurpose" rows="3" required></textarea>
                            <small class="form-text">Please briefly describe the purpose and relevance of your event.</small>
                        </div>

                        <div class="mb-3">
                            <label for="eventWebsite" class="form-label">Event Website Address</label>
                            <input type="url" class="form-control" id="eventWebsite" name="eventWebsite" placeholder="https://example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label required-label">Is this an in-person, virtual, or hybrid event?</label>
                            <select class="form-select" name="eventType" required>
                                <option value="">-- Select --</option>
                                <option value="in-person">In-Person</option>
                                <option value="virtual">Virtual</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="eventDate" class="form-label required-label">Event Date</label>
                            <input type="date" class="form-control" id="eventDate" name="eventDate" required>
                        </div>

                        <div class="mb-3">
                            <label for="eventTime" class="form-label required-label">Event Time</label>
                            <input type="time" class="form-control" id="eventTime" name="eventTime" required>
                        </div>

                        <div class="mb-3">
                            <label for="timeZone" class="form-label required-label">Time Zone</label>
                            <select class="form-select" id="timeZone" name="timeZone" required>
                                <option value="">-- Select --</option>
                                <option value="HST">Hawaii-Aleutian (HST)</option>
                                <option value="AKST">Alaska Standard (AKST)</option>
                                <option value="PST">Pacific (PST)</option>
                                <option value="MST">Mountain (MST)</option>
                                <option value="CST">Central (CST)</option>
                                <option value="EST">Eastern (EST)</option>
                                <option value="AST">Atlantic (AST)</option>
                                <option value="GMT">GMT/UTC</option>
                                <option value="CET">Central European (CET)</option>
                                <option value="IST">India Standard (IST)</option>
                                <option value="JST">Japan Standard (JST)</option>
                                <option value="AEDT">Australian Eastern (AEDT)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required-label">Will the presentation be recorded?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="willBeRecorded" id="recordYes" value="yes" required>
                                <label class="form-check-label" for="recordYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="willBeRecorded" id="recordNo" value="no">
                                <label class="form-check-label" for="recordNo">No</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required-label">Do you expect press to attend?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="expectPress" id="pressYes" value="yes" required>
                                <label class="form-check-label" for="pressYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="expectPress" id="pressNo" value="no">
                                <label class="form-check-label" for="pressNo">No</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required-label">Is there any fee for attendance?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hasFee" id="feeYes" value="yes" required>
                                <label class="form-check-label" for="feeYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hasFee" id="feeNo" value="no">
                                <label class="form-check-label" for="feeNo">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Audience Details Section -->
                    <div class="form-section">
                        <h2 class="form-section-title">Audience Details</h2>

                        <div class="mb-3">
                            <label for="estimatedAttendees" class="form-label">Estimated Number of Attendees</label>
                            <input type="number" class="form-control" id="estimatedAttendees" name="estimatedAttendees" min="0">
                        </div>

                        <div class="mb-3">
                            <label for="audienceType" class="form-label">Audience Type</label>
                            <input type="text" class="form-control" id="audienceType" name="audienceType" placeholder="e.g., students, researchers, industry professionals">
                            <small class="form-text">Examples: students, researchers, industry professionals, general public, etc.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Audience Accessibility</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="audienceAccess" id="accessOpen" value="open">
                                <label class="form-check-label" for="accessOpen">Open to the Public</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="audienceAccess" id="accessInvite" value="invitation">
                                <label class="form-check-label" for="accessInvite">Invitation-Only</label>
                            </div>
                        </div>
                    </div>

                    <!-- Presentation/Speech Details Section -->
                    <div class="form-section">
                        <h2 class="form-section-title">Presentation/Speech Details</h2>

                        <div class="mb-3">
                            <label for="typeOfParticipation" class="form-label required-label">Type of Participation</label>
                            <select class="form-select" id="typeOfParticipation" name="typeOfParticipation" required>
                                <option value="">-- Select --</option>
                                <option value="keynote">Keynote Address</option>
                                <option value="panel">Panel Discussion</option>
                                <option value="lecture">Lecture</option>
                                <option value="workshop">Workshop</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="requestedTopic" class="form-label">Requested Topic</label>
                            <textarea class="form-control" id="requestedTopic" name="requestedTopic" rows="3" placeholder="Please describe the specific topic or subject matter you would like the speaker to address."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="requestedPresenter" class="form-label">Requested Presenter Name</label>
                            <input type="text" class="form-control" id="requestedPresenter" name="requestedPresenter" placeholder="Leave blank if no specific presenter is required">
                        </div>

                        <div class="mb-3">
                            <label for="otherNotes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="otherNotes" name="otherNotes" rows="4" placeholder="Any flexible requirements, deadlines, technical needs, or other relevant information..."></textarea>
                            <small class="form-text">Examples: flexible start/end times, specific deadlines, technical requirements, accessibility needs, etc.</small>
                        </div>
                    </div>

                    <!-- Privacy Section -->
                    <div class="privacy-section">
                        <h3>Privacy Statement</h3>
                        <p>The information you provide on this form will be used to evaluate your speaker request and contact you regarding your event. We take your privacy seriously and will only use your information for the purpose of processing your request.</p>
                        <p>Your email address and contact information will not be shared with third parties unless required by law. We recommend not including sensitive personal information in your submission.</p>
                    </div>

                    <!-- Form Buttons -->
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Clear Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer (optional) -->
    <footer class="bg-white border-top py-3 mt-5">
        <div class="container">
            <p class="text-muted text-center small mb-0">© 2024 Geoffrey E. Hinton. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Honeypot field validation
        function validateHoneypot() {
            const honeypot = document.querySelector('input[name="website"]');
            if (honeypot && honeypot.value.length > 0) {
                console.warn('Honeypot field filled - potential spam');
                return false;
            }
            return true;
        }

        // Show/hide alternate contact field based on selection
        document.getElementById('pointNo').addEventListener('change', function() {
            document.getElementById('alternateContactDiv').style.display = 'block';
            document.getElementById('alternateContact').required = true;
        });
        
        document.getElementById('pointYes').addEventListener('change', function() {
            document.getElementById('alternateContactDiv').style.display = 'none';
            document.getElementById('alternateContact').required = false;
            document.getElementById('alternateContact').value = '';
        });

        // Form submission with fetch
        document.getElementById('speakerRequestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Spam checks
            if (!validateHoneypot()) {
                alert('An error occurred. Please try again.');
                return;
            }

            // Disable submit button to prevent double-submission
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Submitting...';

            try {
                // Create FormData from form
                const formData = new FormData(this);
                
                // Send the request
                const response = await fetch('process-speaker-request.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Show success message
                    alert('✓ Thank you for your speaker request!\n\nWe have received your submission and will review it carefully. You will receive a confirmation email shortly.');
                    
                    // Reset form
                    this.reset();
                    document.getElementById('alternateContactDiv').style.display = 'none';
                    
                    // Scroll to top
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    // Show error message
                    if (response.status === 429) {
                        alert('⚠ Too many submissions.\n\nPlease wait a few minutes before submitting another request.');
                    } else if (response.status === 400 || response.status === 403) {
                        alert('⚠ Validation Error\n\n' + (result.message || 'Please check your input and try again.'));
                    } else {
                        alert('⚠ Submission Failed\n\n' + (result.message || 'Please try again later.'));
                    }
                }
            } catch (error) {
                console.error('Form submission error:', error);
                alert('⚠ An error occurred while submitting your request.\n\nPlease try again.');
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>
