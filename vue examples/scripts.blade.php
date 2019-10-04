@php $locale = substr(session('applocale'), 0, strpos(session('applocale'), "-")) @endphp
@section('scripts')
	<script src='/js/bootstrap-datepicker.min.js'></script>
	<script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/7.2.5/polyfill.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/vue@2.5.22/dist/vue.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/vee-validate@2.1.4/dist/vee-validate.min.js"></script>
	@php $validateLink = sprintf('https://unpkg.com/vee-validate@2.2.9/dist/locale/%s.js', $locale); @endphp
	<script src="{{$validateLink}}"></script>
	<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
	<script src="https://maps.googleapis.com/maps/api/js?key={{env('GOOGLEMAPS_CLIENT_KEY')}}"></script>

	@include('event.create.metronic.scripts.components')  {{-- Because we use vue without npm and in this case cn't use components in .vue formst --}}

	<script type="text/babel" data-presets="es2015,stage-2">

        const dict =
            {
                custom: {
                    group_name: {
                        excluded: () => '{{__('create_event.errors.group_ticket_name')}}'
                    }
                }
            };

        window.onscroll = function () {
            var windowScrollHeight = $('body')[0].scrollHeight;
            var windowHeight = $('body').height();
            var eFooter = $('footer').height() + 60;
            if ($(window).scrollTop() >= windowScrollHeight - windowHeight - eFooter && $(window).width() > 1025) {
                $('#summary-fixed').css('bottom', eFooter);
            } else {
                $('#summary-fixed').css('bottom', '');
            }
        }

        window.Laravel = @json(['csrfToken' => csrf_token()])

            let
        cur = '<?php echo curSym(auth()->user()->company->country->currency)?>';

        let
            minimal_price = <?php echo config('settings.min_ticket_price'); ?>

                let
        galleryImages = @json(gallery_images());

        let free = @json((bool)session('free_event'));

        let id = @json(session('progress'));

        let progress = null;

        Vue.use(VeeValidate, {
            events: 'blur',

        });

        new Vue({
            el: '#create-event',
            components: {
                'summer-note': summernote,
                'modal': modalComp,
                'datetime': datetimeComp,
                'google-map': googleMap
            },
            data: {
                geocoder: '',
                free: Boolean(free),
                count: 0,
                currency: cur,
                event: {
                    address_city: '',
                    address_country: '{{auth()->user()->company->country->id}}',
                    address_line1: '',
                    address_line2: '',
                    address_state: '',
                    address_zip: '',
                    close_type: 'start_time',
                    comments: {
                        comment_text: '',
                        event_file: '',
                        pricing_description: ''
                    },
                    event_close_time: null,
                    event_description: '',
                    event_image: '',
                    event_name: '',
                    event_pass: '',
                    event_password: false,
                    event_scheduled: false,
                    schedule_time: '',
                    locationMap: '',
                    locations: [],
                    seating_type: '',
                    showings: [],
                    filename: '',
                    image_type: '',
                },
                active_ticket: {
                    ticket_name: '',
                    ticket_qty: '',
                    ticket_variations: {},
                    ticket_vars_count: '',
                    groups: {}
                },
                active_group_ticket: {
                    index: '',
                    group_name: '',
                    type: '',
                    options: {},
                    option_vars_count: '',
                    price: '',
                    percent: ''
                },
                textCount: '',
                errorMessages: {
                    errorfile: '',
                    errorimage: '',
                    errorSchedule: '',
                    errorCloseTime: '',
                    errorDescription: '',
                    errorlocation: ''
                },
                images: galleryImages,
                modalGallery: false,
                modalTicketSimpleInfo: false,
                modalTicketAdvancedInfo: false,
                modalAddTicket: false,
                modalCopyTicket: false,
                modalGroupTicket: false,
                currentIndex: '',
                currentTicket: '',
                copy_index: '',
                option_variation: '',
                min_value: minimal_price
            },
            methods: {
                addShowing(params) {
                    let self = this;
                    let i = self.showing_count;
                    let new_showing = {
                        errorDate: false,
                        errorMessage: '',
                        // errorTickets: '',
                        index: i,
                        datetime: '',
                        tickets: {}
                    };
                    if (params) {
                        this.$set(new_showing, 'index', params.index);
                        this.$set(new_showing, 'tickets', this.fillTickets(params.tickets));
                        this.$set(new_showing, 'datetime', params.datetime);
                    }

                    this.$set(this.event.showings, i, new_showing);
                },
                summernoteChange(content) {
                    let descr = content.replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim().split(' ').length;
                    if (descr < 5) {
                        this.errorMessages.errorDescription = '{{__('create_event.validate_text.words')}}'
                    } else {
                        this.errorMessages.errorDescription = ''
                    }
                    this.event.event_description = content;
                },
                datetimeChange(val, i) {
                    this.event.showings[i].datetime = val;
                },
                changeSchedule(val, i) {
                    this.event.schedule_time = val;
                },
                changeCloseTime(val, i) {
                    this.event.event_close_time = val;
                },
                showings_increment() {
                    this.addShowing();
                },
                showings_decrement() {
                    if (this.showing_count > 0) {
                        this.$delete(this.event.showings, this.showing_count - 1);
                    }
                },
                geocodeAddress() {
                    let self = this;
                    self.addClass('find_map');

                    this.geocoder.geocode({
                        'address': self.pretty_address, componentRestrictions: {
                            country: self.country
                        }
                    }, function (results, status) {
                        if (status === google.maps.GeocoderStatus.OK && results[0].formatted_address !== self.country) {
                            let orignalLat = results[0].geometry.location.lat();
                            let originalLong = results[0].geometry.location.lng();
                            if (self.event.locations.length > 0 && self.event.locations[0].lat === orignalLat && self.event.locations[0].lng === originalLong) {
                                customNotify('{{__('create_event.errors.already_placed')}}', 'warning', '')
                            } else {
                                self.$set(self.event.locations, 0, {lat: orignalLat, lng: originalLong});
                                self.errorMessages.errorlocation = false;

                            }
                        } else {
                            customNotify('{{__('create_event.errors.geomap')}}', 'danger', '')
                        }
                    });
                    self.removeClass('find_map');

                },
                getProgress() {
                    let self = this;
                    axios.get("{{app_route('get-progress', ['id'=>session('progress')])}}").then(function (response) {
                        if (response.data === 0) {
                            swal(
                                '{{__('create_event.new_event')}}',
                                '{{__('create_event.errors.download_progress')}}',
                                'info'
                            );
                        } else {
                            self.fillData(response.data);
                        }
                    });
                },
                fillData(response) {
                    let keys = Object.keys(response);
                    for (let key in keys) {
                        let i = keys[key];
                        if (i === 'showings') {
                            this.fillShowings(response[i]);
                        } else {
                            if (i === 'event_description') {
                                this.$refs.editor.run('code', response[i]);
                            }
                            this.$set(this.event, i, response[i]);
                        }
                    }
                },
                fillShowings(data) {
                    for (let index in data) {
                        this.addShowing(data[index]);
                    }
                },
                fillTickets(data) {
                    let tickets = {};
                    for (let index in data) {
                        let variations = data[index].ticket_variations;
                        let groups = data[index].groups;
                        let active = {
                            index: index,
                            ticket_type: data[index].ticket_type,
                            ticket_qty: data[index].ticket_qty,
                            ticket_vars_count: Object.keys(variations).length,
                            ticket_variations: {},
                            groups: {}
                        };

                        for (let variation in variations) {
                            this.$set(active.ticket_variations, variation, this.new_variation(variations[variation]));
                        }

                        for (let group in groups) {
                            this.$set(active.groups, group, this.new_group(groups[group]))
                        }

                        this.$set(tickets, index, active);
                    }
                    return tickets;
                },
                new_group(params) {
                    let group = params;

                    for (let option in params.options) {
                        this.$set(group.options, option, this.new_option(params.options[option]));
                    }

                    return group;

                },
                onFileChange: async function (fieldName, file) {
                    let maxSize = 1024;
                    let imageFile = file[0];
                    let self = this;
                    let errorName = 'error' + fieldName;

                    //check if user actually selected a file
                    self.errorMessages[errorName] = '';
                    if (file.length > 0) {
                        let size = imageFile.size / maxSize / maxSize;
                        if (fieldName === 'image' && !imageFile.type.match('image.*')) {
                            // check whether the upload is an image
                            self.errorMessages[errorName] = '{{__('signup.errors.image.choose')}}';
                        } else if (size > 5) {
                            // check whether the size is greater than the size limit
                            self.errorMessages[errorName] = '{{__('signup.errors.image.big')}}'
                        } else {
                            let formData = new FormData();
                            formData.append(fieldName, imageFile);
                            formData.append('_token', this.token);
                            this.uploadFile(formData, fieldName);
                        }
                    } else {
                        self.errorMessages[errorName] = 'The ' + fieldName + ' is required';
                    }

                    if (self.errorMessages[errorName]) {
                        customNotify(self.errorMessages[errorName], 'danger', '');
                    }
                },
                addClass(ref_name) {
                    this.$refs[ref_name].classList.add("m-loader", "m-loader--light", "m-loader--right")
                },
                removeClass(ref_name) {
                    this.$refs[ref_name].classList.remove("m-loader", "m-loader--light", "m-loader--right")
                },
                uploadFile(data, type) {
                    let self = this;
                    this.addClass('spinner_' + type);
                    let url = '{{app_route('upload-image')}}';
                    if (type === 'file') {
                        url = '{{app_route('upload-file')}}';
                    }

                    axios.post(url, data, {headers: {'Content-Type': 'multipart/form-data'}})
                        .then(function (response) {
                            if (response.data.error) {
                                for (var key in response.data.error) {
                                    if (type === 'image') {
                                        self.errorMessages['error' + type] = response.data.error[key][0];
                                    }
                                    self.removeClass('spinner_' + type);
                                    customNotify(response.data.error[key][0], 'danger', 'Error');
                                    return;
                                }
                            } else {
                                self.removeClass('spinner_' + type);
                                customNotify(type + '{{__('create_event.upload_success')}}', 'success', '');
                                self.event.event_image = response.data.path;
                                self.errorMessages['error' + type] = '';
                            }
                        })
                        .catch(function () {
                            self.removeClass('spinner_' + type);
                            customNotify('{{__('create_event.errors.upload_wrong')}}', 'danger', 'Error');
                        });
                },
                deleteTicket(showing, ticket) {
                    this.$delete(this.event.showings[showing].tickets, ticket);
                },
                reset() {
                    let index = Object.keys(this.event.showings[this.currentIndex].tickets).length;
                    if (index > 0) {
                        index = +Object.keys(this.event.showings[this.currentIndex].tickets).slice(-1)[0] + 1;
                    }
                    let active = {
                        index: index,
                        ticket_type: '',
                        ticket_qty: 0,
                        ticket_vars_count: 1,
                        ticket_variations: {},
                        groups: {}
                    };

                    this.active_ticket = active;
                },
                ticketsModal(index) {
                    this.modalAddTicket = true;
                    this.currentIndex = index;
                    this.reset();
                    this.$set(this.active_ticket.ticket_variations, 0, this.new_variation());
                },
                editTicket(showingIndex, ticketIndex) {
                    this.currentIndex = showingIndex;
                    this.active_ticket = JSON.parse(JSON.stringify(this.event.showings[showingIndex].tickets[ticketIndex]));
                    this.modalAddTicket = true;
                },
                copyTickets() {
                    let self = this;
                    let index = this.copy_index;
                    this.$validator.validateAll('copy').then((result) => {
                        if (result) {
                            let ticket = JSON.parse(JSON.stringify(self.event.showings[index].tickets));

                            for (const [key, value] in self.event.showings) {
                                if (key !== index) {
                                    self.event.showings[key].tickets = JSON.parse(JSON.stringify(ticket));
                                    self.event.showings[key].errorTickets = '';
                                }

                                self.copy_index = '';
                                self.modalCopyTicket = false;
                            }
                        } else {
                            customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                        }
                    }).catch(() => {
                        customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                        return false;
                    });
                },
                validateImage() {
                    if (!Boolean(this.event.event_image)) {
                        this.errorMessages.errorimage = '{{__('create_event.validate_text.image')}}';
                        return false;
                    } else {
                        this.errorMessages.errorimage = '';
                    }

                    return true;
                },
                validateLocation() {
                    this.errorMessages.errorlocation = !this.haveLocation;
                },
                confirmTickets(index) {
                    let tickets = this.event.showings[index].tickets;
                    let ticketIndex = this.active_ticket.index;
                    let scope = 'tickets_' + ticketIndex;
                    let self = this;
                    this.$validator.validateAll(scope).then((result) => {
                        if (result) {
                            if (self.active_ticket.ticket_vars_count === 1) {
                                self.active_ticket.groups = Object.assign({});
                            }
                            self.$set(self.event.showings[index].tickets, ticketIndex, self.active_ticket);
                            self.event.showings[index].errorTickets = '';
                            self.modalAddTicket = false;
                            self.reset();
                        } else {
                            customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                            self.modalAddTicket = true;
                        }
                    }).catch(() => {
                        customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                        return false;
                    });
                },
                increment_variations() {
                    let index = this.active_ticket.ticket_vars_count;
                    this.active_ticket.ticket_vars_count++;
                    this.$set(this.active_ticket.ticket_variations, index, this.new_variation());
                },
                decrement_variations() {
                    let var_index = this.active_ticket.ticket_vars_count;

                    if (var_index > 1) {
                        this.active_ticket.ticket_vars_count--;
                        this.$delete(this.active_ticket.ticket_variations, this.active_ticket.ticket_vars_count);
                    }

                    if (this.active_ticket.ticket_vars_count === 1) {
                        this.active_ticket.ticket_variations[0].ticket_variation_name = '';
                    }
                },
                new_variation(params) {
                    let variation = {
                        ticket_variation_price: '',
                        ticket_variation_name: ''
                    };

                    if (params) {
                        variation.ticket_variation_price = params.ticket_variation_price;
                        variation.ticket_variation_name = params.ticket_variation_name;
                    }
                    if (this.free) {
                        variation.ticket_variation_price = 0;
                    }

                    return variation;
                },
                new_option(params) {
                    let option = {
                        variation_id: '',
                        variation_qty: ''
                    };

                    if (params) {
                        option.variation_id = params.variation_id;
                        option.variation_qty = params.variation_qty;
                    }

                    return option;
                },
                validateDates() {
                    if (this.event.event_scheduled && !this.event.schedule_time) {
                        this.errorMessages.errorSchedule = 'Schedule time field is required';
                    } else {
                        this.errorMessages.errorSchedule = '';
                    }

                    if (this.event.close_type === 'custom' && !this.event.event_close_time) {
                        this.errorMessages.errorCloseTime = 'Close time field is required';
                    } else {
                        this.errorMessages.errorCloseTime = '';
                    }
                },
                group_ticket(showingIndex, ticketIndex) {
                    this.currentIndex = showingIndex;
                    this.currentTicket = ticketIndex;
                    this.reset_group();
                    this.$set(this.active_group_ticket.options, 0, this.new_option());
                },
                add_group_option() {
                    let index = Object.keys(this.active_group_ticket.options).length;

                    if (index < Object.keys(this.event.showings[this.currentIndex].tickets[this.currentTicket].ticket_variations).length) {
                        this.active_group_ticket.option_vars_count++;
                        this.$set(this.active_group_ticket.options, index, this.new_option());
                    } else {
                        customNotify('{{__('create_event.errors.more_than_variations')}}', 'warning', '');
                    }
                },
                delete_group_option() {
                    let var_index = this.active_group_ticket.option_vars_count;
                    if (var_index > 1) {
                        this.active_group_ticket.option_vars_count--;
                        this.$delete(this.active_group_ticket.options, this.active_group_ticket.option_vars_count);
                    }
                },
                deleteGroup(id, tickId, showId) {
                    this.$delete(this.event.showings[showId].tickets[tickId].groups, id);
                },
                reset_group() {
                    let index = Object.keys(this.event.showings[this.currentIndex].tickets[this.currentTicket].groups).length;
                    if (index > 0) {
                        index = +Object.keys(this.event.showings[this.currentIndex].tickets[this.currentTicket].groups).slice(-1)[0] + 1;
                    }

                    let active_group_ticket = {
                        index: index,
                        group_name: '',
                        type: '',
                        options: {},
                        option_vars_count: 1,
                        price: '',
                        percent: ''
                    };

                    this.$set(this, 'active_group_ticket', active_group_ticket);
                },
                confirmGroup() {
                    let scope = 'group';
                    let self = this;
                    let groupIndex = this.active_group_ticket.index;
                    this.$validator.validateAll(scope).then((result) => {
                        if (result) {
                            self.$set(self.event.showings[this.currentIndex].tickets[this.currentTicket].groups, groupIndex, self.active_group_ticket);
                            self.modalGroupTicket = false;
                            self.reset_group();
                        } else {
                            customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                            self.modalGroupTicket = true;
                        }
                    }).catch(() => {
                        customNotify('{{__('signup.errors.check')}}', 'danger', 'Error');
                        return false;
                    });
                },
                saveProgress() {
                    let self = this;
                    swal({
                        title: "{{__('create_event.save_progress')}}",
                        text: "{{__('create_event.continue_setup')}}",
                        type: "question",
                        showCancelButton: true,
                        showLoaderOnConfirm: true,
                        confirmButtonText: "{{__('create_event.save_progress')}}",
                        preConfirm: function () {
                            return new Promise(function (resolve) {
                                setTimeout(function () {
                                    resolve()
                                }, 3000)
                            })
                        }
                    }).then(function (isConfirm) {
                        if (isConfirm.value) {
                            axios.post("{{app_route('create-progress')}}", {
                                'data': JSON.stringify(self.$data.event),
                                'id': id,
                                'free': self.free
                            }, {headers: {'Content-Type': 'application/json'}})
                                .then(function (response) {
                                    location.href = '{{app_route('my.events')}}';

                                });
                        } else if (isConfirm.dismiss) {
                            swal(
                                '{{__('create_event.cancel')}}',
                                '{{__('create_event.fill')}}',
                                'info'
                            )
                        }
                    });
                },
                save_event() {
                    let self = this;
                    this.validateLocation();
                    this.validateShowings();
                    this.validateImage();
                    this.summernoteChange(this.event.event_description);
                    this.$validator.validateAll().then((result) => {
                        if (result && self.progress == 100) {

                            let event = self.$data.event;
                            event.free = self.free;
                            event._token = self.token;
                            axios.post('{{app_route('save-event')}}', {data: JSON.stringify(event)}, {headers: {'Content-Type': 'application/json'}})
                                .then(function (response) {
                                    location.href = '{{app_route('my.events')}}';

                                });
                        }

                    }).catch(() => {
                        customNotify('{{__('create_event.errors.create_event')}}', 'danger', 'Error');
                        return false
                    });
                },
                validateShowings() {
                    this.event.showings.forEach(function (key, value) {
                        if (!key.datetime) {
                            key.errorDate = true;
                            key.errorMessage = '{{__('create_event.validate_text.showing_date')}}';
                        }
                        if (Object.keys(key.tickets).length === 0) {
                            key.errorTickets = '{{__('create_event.validate_text.one_ticket')}}';
                        }
                    });

                    let showingErrors = Object.values(this.event.showings).filter(function (key, value) {
                        return key.errorMessage || key.errorTickets;
                    });

                    if (showingErrors.length > 0) {
                        return false;
                    }

                    return true;

                },
                address_pretty_builder(address, pretty_str) {
                    if (address) {
                        if (address.length > 0) {
                            if (pretty_str !== '') {
                                pretty_str = pretty_str.concat(', ');
                            }
                            pretty_str = pretty_str.concat(address);
                        }
                    }
                    return pretty_str;
                },
            },
            computed: {
                pretty_address() {
                    var address_pretty_str = '';
                    address_pretty_str = this.address_pretty_builder(this.event.address_line1, address_pretty_str);
                    address_pretty_str = this.address_pretty_builder(this.event.address_line2, address_pretty_str);
                    address_pretty_str = this.address_pretty_builder(this.event.address_city, address_pretty_str);
                    address_pretty_str = this.address_pretty_builder(this.event.address_state, address_pretty_str);
                    address_pretty_str = this.address_pretty_builder(this.event.address_zip, address_pretty_str);
                    address_pretty_str = this.address_pretty_builder(this.country, address_pretty_str);
                    return address_pretty_str;
                },
                button_text() {
                    return this.event.event_scheduled ? '{{__('create_event.schedule')}}' : '{{__('create_event.publish')}}';
                },
                showing_count() {
                    this.count = this.event.showings.length;
                    return this.count;
                },
                min_price() {
                    if (this.free) {
                        return '0';
                    } else {
                        return minimal_price;
                    }
                },
                ticket_style() {
                    if (this.event.seating_type == '{{\App\Event::TYPE_ADVANCED}}') {
                        return '{{__('create_event.advanced_ticket')}}';
                    } else if (this.event.seating_type == '{{\App\Event::TYPE_SIMPLE}}') {
                        return '{{__('create_event.simple_ticket')}}';
                    }
                    return '';
                },
                country() {
                    return '{{auth()->user()->company->country->name}}';
                },
                token() {
                    return window.Laravel.csrfToken;
                },
                progress_width() {
                    return this.progress;
                },
                description_length() {
                    return this.event.event_description.replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim().split(' ').length;
                },
                haveDate() {
                    if (this.event.showings.length > 0) {
                        function isHaveDate(number) {
                            return number.datetime !== '';
                        }

                        return this.event.showings.every(isHaveDate);
                    }
                    return false;
                },
                showing_tickets() {
                    if (!Boolean(this.event.seating_type) || this.event.showings.length === 0) {
                        return false
                    }
                    if (this.event.seating_type === '{{\App\Event::TYPE_SIMPLE}}') {
                        var isHaveTickets = function (number) {
                            return Object.keys(number.tickets).length > 0;
                        };

                        return this.event.showings.every(isHaveTickets);
                    } else {
                        return Boolean(this.event.comments.comment_text) && Boolean(this.event.comments.pricing_description);
                    }

                    return false;
                },
                haveLocation() {
                    if (!this.event.address_line1 || !this.event.address_city || !this.event.address_state || !this.event.address_zip) {
                        return false;
                    } else {
                        return true;
                    }
                },
                progress() {
                    let progressState = [
                        this.event.event_name.length >= 3,
                        this.description_length >= 5,
                        Boolean(this.event.seating_type),
                        Boolean(this.event.event_image),
                        Boolean(this.event.image_type),
                        this.event.showings.length > 0,
                        this.haveDate,
                        this.showing_tickets,
                        this.event.locations.length > 0,
                        this.event.locationMap,
                    ];

                    let complete = progressState.filter(function (value) {
                        return value === true;
                    }).length;

                    return (complete / progressState.length) * 100;
                },
                disable_title() {
                    return this.progress < 100 ? '{{__('create_event.errors.complete')}}' : '';
                }
            },
            filters: {
                toFloat(price, bits) {
                    return parseFloat(price).toFixed(bits);
                },
                prettyName(name) {
                    if (name.length > 0) {
                        return name;
                    } else {
                        return 'N/A'
                    }
                },
                dateToIndex(date, index, string) {
                    let str = date;
                    if (date.length == 0) {
                        str = '{{__('create_event.showing')}} ' + ++index;
                    }
                    return str;
                }
            },
            mounted() {
                if (id) {
                    this.getProgress();
                }
                if (this.free) {
                    this.event.seating_type = '{{\App\Event::TYPE_SIMPLE}}';
                }

                this.geocoder = new google.maps.Geocoder();
                VeeValidate.Validator.extend('uniqueInArray', {
                    getMessage: (field) => `The ${field} should be unique.`,
                    validate: (value, args) => {
                        var unique = args.filter(function (v, i, a) {
                            return a.indexOf(v) === i || v === '';
                        });

                        if (unique.length !== args.length) {
                            return false;
                        }

                        return true;
                    }
                });
                VeeValidate.Validator.extend('atLeastOneNotFree', {
                    getMessage: (field) => `At least one of the ${field} should be equal or greater than 0.10.`,
                    validate: (value, args) => {

                        var min_price = args.filter(function (value) {
                            return value >= minimal_price;
                        });

                        if (min_price.length > 0) {
                            return true;
                        }

                        return false;
                    }
                });
                VeeValidate.Validator.localize('{{$locale}}', dict);

            }
        });
	</script>
@endsection
