<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

        .premium-form-container {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 10% 20%, rgba(240, 243, 255, 0.5) 0%, rgba(255, 255, 255, 0.7) 90%);
        }

        .premium-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(102, 126, 234, 0.12), 
                        0 0 50px 0 rgba(102, 126, 234, 0.05);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-card:hover {
            box-shadow: 0 30px 60px -20px rgba(102, 126, 234, 0.18), 
                        0 0 60px 0 rgba(102, 126, 234, 0.08);
        }

        .form-header-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
            position: relative;
            overflow: hidden;
        }

        .form-header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 60%);
            animation: rotate-bg 20s linear infinite;
        }

        @keyframes rotate-bg {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .premium-input {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: rgba(248, 250, 252, 0.8);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .premium-select-multi {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: rgba(248, 250, 252, 0.8);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-select-multi option {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .premium-select-multi option:checked {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            color: white !important;
        }

        .section-label {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .custom-upload-zone {
            border: 2px dashed #cbd5e1;
            background: rgba(248, 250, 252, 0.6);
            border-radius: 16px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .custom-upload-zone:hover {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.03);
        }

        .premium-button {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: -0.01em;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .premium-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s;
        }

        .premium-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .premium-button:hover::after {
            left: 100%;
        }

        .premium-button:active {
            transform: translateY(0);
        }

        .form-section-card {
            background: rgba(248, 250, 252, 0.4);
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 20px;
        }
    </style>

    <div class="py-12 premium-form-container min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Main Premium Form Card -->
            <div class="premium-card overflow-hidden">
                
                <!-- Radiant Gradient Header -->
                <div class="form-header-gradient p-8 text-white text-center">
                    <div class="inline-flex p-3 bg-white/10 backdrop-blur-md rounded-2xl mb-4 border border-white/20">
                        <svg class="w-8 height-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-extrabold tracking-tight">Log New NMBA Event</h2>
                    <p class="text-indigo-100/90 mt-2 font-medium">Record activity details securely and enqueue for automated portal synchronization.</p>
                </div>

                <div class="p-8">
                    @if ($errors->any())
                        <div class="mb-8 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl text-rose-800 shadow-sm animate-pulse">
                            <div class="flex items-center gap-2 mb-2 font-bold">
                                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>Form Validation Issue:</span>
                            </div>
                            <ul class="list-disc list-inside text-sm space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                        @csrf

                        <!-- Section: Location & Basic Info -->
                        <div class="form-section-card space-y-6">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b pb-3 border-gray-100">
                                <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></span>
                                Basic Information & Location
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">District</label>
                                    <input type="text" value="Budgam" disabled class="w-full premium-input p-3 border-gray-200 bg-gray-100/80 text-gray-500 font-medium">
                                </div>

                                <div>
                                    <label for="block_id" class="block text-sm font-semibold text-gray-700 mb-1">Block <span class="text-red-500">*</span></label>
                                    <select name="block_id" id="block_id" required class="w-full premium-input p-3 border-gray-200 focus:ring-indigo-500/20 text-gray-800 font-medium">
                                        <option value="">-- Select Block Location --</option>
                                        @foreach($blocks as $id => $name)
                                            <option value="{{ $id }}" {{ old('block_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="ward" class="block text-sm font-semibold text-gray-700 mb-1">Ward</label>
                                    <input type="text" name="ward" id="ward" value="{{ old('ward') }}" placeholder="Enter ward name" class="w-full premium-input p-3 border-gray-200">
                                </div>
                                <div>
                                    <label for="village" class="block text-sm font-semibold text-gray-700 mb-1">Village</label>
                                    <input type="text" name="village" id="village" value="{{ old('village') }}" placeholder="Enter village name" class="w-full premium-input p-3 border-gray-200">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Event Specifics -->
                        <div class="form-section-card space-y-6">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b pb-3 border-gray-100">
                                <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 01-2 2h0a2 2 0 01-2-2v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg></span>
                                Event Specifics & Details
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="event_name" class="block text-sm font-semibold text-gray-700 mb-1">Event Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="event_name" id="event_name" value="{{ old('event_name') }}" required placeholder="Enter event name" class="w-full premium-input p-3 border-gray-200">
                                </div>

                                <div>
                                    <label for="event_date" class="block text-sm font-semibold text-gray-700 mb-1">Event Date <span class="text-red-500">*</span></label>
                                    <input type="date" name="event_date" id="event_date" max="{{ date('Y-m-d') }}" value="{{ old('event_date') }}" required class="w-full premium-input p-3 border-gray-200">
                                </div>
                            </div>

                            <div>
                                <label for="event_venue" class="block text-sm font-semibold text-gray-700 mb-1">Event Venue <span class="text-red-500">*</span></label>
                                <input type="text" name="event_venue" id="event_venue" value="{{ old('event_venue') }}" required placeholder="Enter venue location" class="w-full premium-input p-3 border-gray-200">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Event Category <span class="text-red-500">*</span></label>
                                <select multiple name="event_category[]" id="event_category" required class="w-full premium-select-multi p-2 border-gray-200 h-32 focus:ring-indigo-500/20">
                                    @php
                                        $categories = [
                                            'Cultural' => 'Cultural - Song, Dance, Nukkad Natak, etc',
                                            'Awareness' => 'Awareness - Legal Session, etc',
                                            'Sports' => 'Sports - Padyatra, Marathon, etc',
                                            'Training & Counselling' => 'Training & Counselling',
                                            'Others' => 'Others'
                                        ];
                                    @endphp
                                    @foreach($categories as $value => $label)
                                        <option value="{{ $value }}" {{ is_array(old('event_category')) && in_array($value, old('event_category')) ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                
                                <div class="mt-4 animate-fade-in" id="category_remark_wrapper" style="display: none;">
                                    <label for="event_category_remark" class="block text-sm font-semibold text-rose-700 mb-1">Category Remark <span class="text-red-500">*</span> <span class="text-xs font-normal text-rose-500">(Required when 'Others' is selected)</span></label>
                                    <input type="text" name="event_category_remark" id="event_category_remark" value="{{ old('event_category_remark') }}" placeholder="Enter remark (max 20 words)" class="w-full premium-input p-3 border-rose-300 bg-rose-50/20 focus:border-rose-500 focus:ring-rose-500/10">
                                    <div class="mt-1 flex justify-between text-xs text-gray-500">
                                        <span>Provide context for 'Others'</span>
                                        <span id="word_counter_label" class="font-semibold text-slate-500">0 / 20 words</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Demographics & Attendance -->
                        <div class="form-section-card space-y-6">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b pb-3 border-gray-100">
                                <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></span>
                                Attendance & Targeted Demographics
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="attendance_range" class="block text-sm font-semibold text-gray-700 mb-1">Attendance Range <span class="text-red-500">*</span></label>
                                    <select name="attendance_range" id="attendance_range" required class="w-full premium-input p-3 border-gray-200">
                                        <option value="">=> Select Range</option>
                                        <option value="20-40" {{ old('attendance_range') == '20-40' ? 'selected' : '' }}>20-40</option>
                                        <option value="40-100" {{ old('attendance_range') == '40-100' ? 'selected' : '' }}>40-100</option>
                                        <option value="100-150" {{ old('attendance_range') == '100-150' ? 'selected' : '' }}>100-150</option>
                                        <option value="150-200" {{ old('attendance_range') == '150-200' ? 'selected' : '' }}>150-200</option>
                                        <option value="200-500" {{ old('attendance_range') == '200-500' ? 'selected' : '' }}>200-500</option>
                                        <option value="500 & above" {{ old('attendance_range') == '500 & above' ? 'selected' : '' }}>500 & above</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="actual_attendance" class="block text-sm font-semibold text-gray-700 mb-1">Attendance <span class="text-red-500">*</span></label>
                                    <input type="number" name="actual_attendance" id="actual_attendance" min="1" value="{{ old('actual_attendance') }}" required placeholder="Enter actual count" class="w-full premium-input p-3 border-gray-200">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience <span class="text-red-500">*</span></label>
                                    <select multiple name="target_audience[]" id="target_audience" required class="w-full premium-select-multi p-2 border-gray-200 h-32 focus:ring-indigo-500/20">
                                        @foreach(['Civil Society', 'Students', 'Youth', 'Transporters', 'Other'] as $aud)
                                            <option value="{{ $aud }}" {{ is_array(old('target_audience')) && in_array($aud, old('target_audience')) ? 'selected' : '' }}>{{ $aud }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Age Group <span class="text-red-500">*</span></label>
                                    <select multiple name="age_group[]" id="age_group" required class="w-full premium-select-multi p-2 border-gray-200 h-32 focus:ring-indigo-500/20">
                                        @foreach(['Under 18', '18-25', '25-35', '35-45', '45-55', 'Above 55'] as $age)
                                            <option value="{{ $age }}" {{ is_array(old('age_group')) && in_array($age, old('age_group')) ? 'selected' : '' }}>{{ $age }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Event Coordinator -->
                        <div class="bg-indigo-50/50 border border-indigo-100 p-6 rounded-2xl space-y-6">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b pb-3 border-indigo-100/50">
                                <span class="p-1.5 bg-indigo-100 text-indigo-700 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></span>
                                Event Coordinator details
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="event_coordinator_name" class="block text-sm font-semibold text-gray-700 mb-1">Coordinator Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="event_coordinator_name" id="event_coordinator_name" value="{{ old('event_coordinator_name') }}" required placeholder="Enter coordinator name" class="w-full premium-input p-3 border-gray-200">
                                </div>
                                <div>
                                    <label for="event_coordinator_contact_number" class="block text-sm font-semibold text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="event_coordinator_contact_number" id="event_coordinator_contact_number" placeholder="Enter 10-digit mobile number" value="{{ old('event_coordinator_contact_number') }}" required class="w-full premium-input p-3 border-gray-200">
                                </div>
                                <div>
                                    <label for="event_coordinator_desig" class="block text-sm font-semibold text-gray-700 mb-1">Designation <span class="text-red-500">*</span></label>
                                    <input type="text" name="event_coordinator_desig" id="event_coordinator_desig" value="{{ old('event_coordinator_desig') }}" required placeholder="Enter designation" class="w-full premium-input p-3 border-gray-200">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Photo Uploads -->
                        <div class="form-section-card space-y-4">
                            <label for="photo" class="block text-sm font-semibold text-gray-700">Upload Photos <span class="text-red-500">*</span> <span class="text-xs font-normal text-gray-500">(1-3 photos, max 5MB each)</span></label>
                            
                            <div class="custom-upload-zone p-8 text-center" id="upload-zone">
                                <input type="file" name="photo[]" id="photo" accept="image/jpeg,image/png,image/gif" multiple required class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                <div class="space-y-2 pointer-events-none">
                                    <div class="inline-flex p-3 bg-indigo-50 text-indigo-600 rounded-full mb-1">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <p class="text-sm font-bold text-gray-700" id="file-count-text">Click to select files, or drag them here</p>
                                    <p class="text-xs text-gray-400">Supported formats: JPEG, PNG, GIF up to 5MB</p>
                                </div>
                            </div>
                            <div id="file-list-preview" class="grid grid-cols-3 gap-4 pt-2"></div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end pt-6 border-t border-gray-100 gap-4">
                            <a href="{{ route('events.dashboard') }}" class="inline-flex justify-center items-center py-3 px-6 border border-gray-300 text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition">Cancel</a>
                            <button type="submit" class="premium-button text-white py-3 px-8 shadow-md">Create Event</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Event Category - Show/Hide Remark field
            const eventCategory = document.getElementById('event_category');
            const remarkWrapper = document.getElementById('category_remark_wrapper');
            const remarkInput = document.getElementById('event_category_remark');
            const wordCounter = document.getElementById('word_counter_label');

            function toggleCategoryRemark() {
                const selectedOptions = Array.from(eventCategory.selectedOptions).map(opt => opt.value);
                if (selectedOptions.includes('Others')) {
                    remarkWrapper.style.display = 'block';
                    remarkInput.setAttribute('required', 'required');
                } else {
                    remarkWrapper.style.display = 'none';
                    remarkInput.removeAttribute('required');
                    remarkInput.value = '';
                    updateWordCount();
                }
            }

            function updateWordCount() {
                const text = remarkInput.value.trim();
                const words = text ? text.split(/\s+/).filter(w => w.length > 0) : [];
                wordCounter.textContent = `${words.length} / 20 words`;
                
                if (words.length > 20) {
                    remarkInput.classList.add('border-rose-500', 'bg-rose-50');
                    wordCounter.classList.add('text-rose-600');
                    remarkInput.setCustomValidity('Maximum 20 words allowed.');
                } else {
                    remarkInput.classList.remove('border-rose-500', 'bg-rose-50');
                    wordCounter.classList.remove('text-rose-600');
                    remarkInput.setCustomValidity('');
                }
            }

            if (eventCategory) {
                eventCategory.addEventListener('change', toggleCategoryRemark);
                toggleCategoryRemark();
            }

            if (remarkInput) {
                remarkInput.addEventListener('input', updateWordCount);
                updateWordCount();
            }

            // Image Upload Zone - UI Handling & Preview
            const fileInput = document.getElementById('photo');
            const fileCountText = document.getElementById('file-count-text');
            const filePreview = document.getElementById('file-list-preview');

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const count = this.files.length;
                    if (count > 0) {
                        fileCountText.textContent = `${count} file(s) selected`;
                        fileCountText.classList.add('text-indigo-600');
                        
                        // Clear current previews
                        filePreview.innerHTML = '';
                        
                        // Generate new image previews
                        Array.from(this.files).slice(0, 3).forEach((file, index) => {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const previewItem = document.createElement('div');
                                previewItem.className = 'relative rounded-lg overflow-hidden border border-slate-200 shadow-sm aspect-video bg-cover bg-center h-20';
                                previewItem.style.backgroundImage = `url('${e.target.result}')`;
                                filePreview.appendChild(previewItem);
                            };
                            reader.readAsDataURL(file);
                        });
                    } else {
                        fileCountText.textContent = 'Click to select files, or drag them here';
                        fileCountText.classList.remove('text-indigo-600');
                        filePreview.innerHTML = '';
                    }
                });
            }

            // Real-Time Attendance Range Auto-Selection and Validation
            const actualAttendanceInput = document.getElementById('actual_attendance');
            const attendanceRangeSelect = document.getElementById('attendance_range');

            function getExpectedAttendanceRange(count) {
                if (count <= 40) return '20-40';
                if (count <= 100) return '40-100';
                if (count <= 150) return '100-150';
                if (count <= 200) return '150-200';
                if (count <= 500) return '200-500';
                return '500 & above';
            }

            function autoSelectAndValidateAttendance() {
                const count = parseInt(actualAttendanceInput.value, 10);
                if (isNaN(count) || count <= 0) {
                    attendanceRangeSelect.setCustomValidity('');
                    return;
                }

                const expectedRange = getExpectedAttendanceRange(count);
                
                // Automatically set the correct range selection
                attendanceRangeSelect.value = expectedRange;
                attendanceRangeSelect.setCustomValidity('');
            }

            if (actualAttendanceInput && attendanceRangeSelect) {
                actualAttendanceInput.addEventListener('input', autoSelectAndValidateAttendance);
                actualAttendanceInput.addEventListener('change', autoSelectAndValidateAttendance);
                
                // Enforce validation if the user manually changes the select dropdown to an invalid option
                attendanceRangeSelect.addEventListener('change', function() {
                    const count = parseInt(actualAttendanceInput.value, 10);
                    if (!isNaN(count) && count > 0) {
                        const expectedRange = getExpectedAttendanceRange(count);
                        if (this.value !== expectedRange) {
                            this.setCustomValidity(`Attendance of ${count} requires the '${expectedRange}' range.`);
                        } else {
                            this.setCustomValidity('');
                        }
                    }
                });

                // Run initially on load (e.g. if old inputs exist)
                if (actualAttendanceInput.value) {
                    autoSelectAndValidateAttendance();
                }
            }
        });
    </script>
</x-app-layout>

