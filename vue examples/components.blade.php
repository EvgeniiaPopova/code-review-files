<script type="text/babel" data-presets="es2015,stage-2">
    let summernote = {
        data() {
            return {
                text: '',
            };
        },
        template: `<textarea ref="summernote" id="summernote" />`,
        computed: {
            summernote() {
                return $(this.$refs.summernote);
            }
        },
        mounted() {
            let vm = this;
            $(this.$refs.summernote).summernote({
                height: 150,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline']],
                ],
                callbacks: {
                    onChange: function (contents) {
                        this.text = contents;
                        vm.$emit('description', this.text);
                    }
                }
            });
        },
        methods: {
            run(code, value) {
                if (value == undefined) {
                    $(this.$refs.summernote).summernote(code)
                } else {
                    $(this.$refs.summernote).summernote(code, value)
                }
            }
        }
    };

    let modalComp = {
        props: {
            name: String,
            button_name: {
                type: String,
                default: ''
            },
            subtitle: String,
            size: String,
            button_style: {
                type: String,
                default: 'btn-primary'
            }
        },
        template: `
        <div class="modal show modal-open modal-vue" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" style="padding-right: 15px; display: block; background: rgba(0, 0, 0, 0.3); box-shadow: 2px 2px 20px 1px;">
            <div class="modal-dialog modal-dialog-centered"  :class="size" role="document">
                <div class="modal-content" >
                    <div class="modal-header">
                        <h5 class="modal-title">
                            @{{ name }}
                            <br>
                            <span><small>@{{ subtitle }}</small></span>
                        </h5>
                        <button type="button" @click="$emit('close')" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <slot></slot>
                    </div>
                    <div class="modal-footer" v-if="button_name !== ''">
                        <button type="button" class="btn" :class="button_style"  @click="$emit('action')">@{{ button_name }}</button>
                    </div>
                </div>
            </div>
        </div>`
    };

    let googleMap = {
        props: {
            lat: {
                type: Number,
                default: 0
            },
            lng: {
                type: Number,
                default: 0
            }
        },
        template: `<div class="google-map" id="google-map" style="height:200px; width:100%;"></div>`,
        data: function () {
            return {
                map: '', markers: {
                    latitude: this.lat,
                    longitude: this.lng
                }, marker: ''
            }
        },
        watch: {
            '$props': {
                handler: function (val, oldVal) {
                    if (val.lat !== 0 && val.lng !== 0) {
                        let coords = new google.maps.LatLng(val.lat, val.lng);
                        let marker = {
                            position: coords,
                            map: this.map,
                        };
                        if (this.marker) {
                            this.marker.setMap(null);
                        }
                        this.marker = new google.maps.Marker(marker);
                        this.map.setCenter(coords);
                        this.map.setZoom(12);
                    }
                },
                deep: true
            }
        },
        mounted: function () {
            const element = document.getElementById('google-map');
            const options = {
                geocoder: null,
                zoom: 1,
                center: {lat: this.lat, lng: this.lng},
                disableDefaultUI: true,
                scaleControl: true,
                zoomControl: true,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };
            this.map = new google.maps.Map(element, options);

        },
    };

    let datetimeComp = {
        props: {
            index: {
                type: [Number, String],
                default: ''
            },
            format: String,
            input_name: String,
            value: {
                type: [String, Date],
                default: null
            },
            placeholder: String
        },
        data() {
            return {date: ''};
        },
        template: `<div class="input-group">
					<input type="text" class="form-control showingDateTimeSelector" readonly :name="input_name+index"
					 :placeholder="placeholder" ref="input" :id="input_name+index" >
					</div>`,
        mounted() {
            let id = "#" + this.input_name + this.index;
            if (this.value) {
                this.$refs.input.value = this.value;
            }
            let vm = this;
            $(this.$refs.input).datetimepicker({
                todayHighlight: true,
                autoclose: true,
                pickerPosition: 'top-left',
                format: this.format,
                startDate: new Date()
            })
                .on(
                    'outOfRange', (e) => {
                        customNotify('Out of Range Date', 'danger', 'error');
                    })
                .on(
                    "changeDate", () => {
                        this.$emit('datetimechanged', this.$refs.input.value, this.index);
                    }
                );
        }
    };
</script>