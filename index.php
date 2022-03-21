<!--
	WebXMB (Web Cross Media Bar) - Standalone WebGL based XMB Interface.

	Copyright (C) 2021 - Tomas Radavicius (Raven)

	WebXMB is free software: you can redistribute it and/or modify it under the terms
	of the GNU General Public License as published by the Free Software Found-
	ation, either version 3 of the License, or (at your option) any later version.

	WebXMB is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
	without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
	PURPOSE.  See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with RetroArch.
	If not, see <http://www.gnu.org/licenses/>.
-->
<!doctype html>
<html>
	<head>
		<title>WebXMB</title>
		<!-- Configuration CSS File -->
		<link rel="stylesheet" href="./styles/default/css/config.css">
        <link rel="stylesheet" href="./styles/default/css/main.css">
		<script src="./config.js"></script>
		<script src="./assets/regl/regl.min.js"></script>
        <!-- "Waves" engine -->
        <script src="./assets/js/waves.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.0.3/vue.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/zepto/1.2.0/zepto.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.15.0/lodash.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
        <script src="//cdn.jsdelivr.net/npm/jquery.marquee@1.5.0/jquery.marquee.min.js" type="text/javascript"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/velocity/2.0.6/velocity.min.js"></script>
		<script id="BackgroundVertex" type="x-shader/x-vertex">
			precision lowp float;
	
			attribute vec2 position;
	
			uniform vec2 resolution;
			
			varying highp vec2 pos;
			varying float gradient;
	
			void main() {
				pos = ( position + 1.0 ) / 2.0 * resolution;
				gradient = 1.0 - position.y * 0.625;
				gl_Position = vec4( position, 0.0, 1.0 );
			}
		</script>
		<script id="BackgroundFragment" type="x-shader/x-fragment">
			precision lowp float;
	
			uniform vec3 color;
			uniform vec2 resolution;
			uniform sampler2D bayerTexture;
			
			varying highp vec2 pos;
			varying float gradient;
			
			const float colorDepth = 255.0;

			vec3 dither( vec2 position, vec3 color ) {
				float threshold = texture2D( bayerTexture, position / 8.0 ).a;
				vec3 diff = 1.0 - mod( color * colorDepth, 1.0 );
				return color + diff * vec3(
						float( diff.r < threshold ),
						float( diff.g < threshold ),
						float( diff.b < threshold )
					) / colorDepth;
			}

			void main() {
				gl_FragColor = vec4( dither( pos, gradient * color ), 1.0 );
			}
		</script>
		<script id="FlowVertex" type="x-shader/x-vertex">
			precision lowp float;
	
			attribute vec2 position;
	
			uniform float time;
			uniform float ratio;
			uniform float step;
			uniform float opacity;
	
			varying float alpha;
	
			float iqhash( float n ) {
				return fract( sin( n ) * 43758.5453 );
			}
	
			float noise( vec3 x ) {
				vec3 f = fract( x );
				f = f * f * ( 3.0 - 2.0 * f );
				float n = dot( floor( x ), vec3( 1.0, 57.0, 113.0 ) );
				return mix(
							mix( mix( iqhash( n +   0.0 ), iqhash( n +   1.0 ), f.x ),
								 mix( iqhash( n +  57.0 ), iqhash( n +  58.0 ), f.x ),
								 f.y ),
							mix(
								 mix( iqhash( n + 113.0 ), iqhash( n + 114.0 ), f.x ),
								 mix( iqhash( n + 170.0 ), iqhash( n + 171.0 ), f.x ),
								 f.y ),
							f.z );
			}
	
			vec3 getVertex( float x, float y ) {
				vec3 vertex = vec3( x, cos( y * 4.0 ) * cos( y + time / 5.0 + x ) / 8.0, y );
	
				float c = noise( vertex * vec3( 7.0 / 4.0, 7.0, 7.0 ) ) / 15.0;
				vertex.y += c + cos( x * 2.0 - time ) * ratio / 3.0 - 0.3;
				vertex.z += c;
	
				return vertex;
			}
	
			void main() {
				gl_Position = vec4( getVertex( position.x, position.y ), 1.0 );
	
				vec3 dfdx = getVertex( position.x + step, position.y ) - gl_Position.xyz;
				vec3 dfdy = getVertex( position.x, position.y + step ) - gl_Position.xyz;
				alpha = 1.0 - abs( normalize( cross( dfdx, dfdy ) ).z );
				alpha = ( 1.0 - cos( alpha * alpha ) ) * opacity;
			}
		</script>
		<script id="FlowFragment" type="x-shader/x-fragment">
			precision lowp float;
	
			varying float alpha;
	
			void main() {
				gl_FragColor = vec4( alpha, alpha, alpha, 1.0 );
			}
		</script>
		<script id="ParticleVertex" type="x-shader/x-vertex">
			precision lowp float;
	
			attribute vec3 seed;
			uniform float time;
			uniform float ratio;
			uniform float opacity;
	
			varying float alpha;
	
			float getWave( float x, float y ) {
				return cos( y * 4.0 ) * cos( x + y + time / 5.0 ) / 8.0 + cos( x * 2.0 - time ) * ratio / 3.0 - 0.28;
			}
	
			void main() {
				gl_PointSize = seed.z;
	
				float x = fract( time * ( seed.x - 0.5 ) / 15.0 + seed.y * 50.0 ) * 2.0 - 1.0;
				float y = sin( sign( seed.y ) * time * ( seed.y + 1.5 ) / 4.0 + seed.x * 100.0 );
				y /= ( 6.0 - seed.x * 4.0 * seed.y ) / ratio;
	
				float opacityVariance = mix(
					sin( time * ( seed.x + 0.5 ) * 12.0 + seed.y * 10.0 ),
					sin( time * ( seed.y + 1.5 ) * 6.0 + seed.x * 4.0 ),
					y * 0.5 + 0.5 ) * seed.x + seed.y;
				alpha = opacity * opacityVariance * opacityVariance;
	
				y += getWave( x, seed.y );
	
				gl_Position = vec4( x, y, 0.0, 1.0 );
			}
		</script>
		<script id="ParticleFragment" type="x-shader/x-fragment">
			precision lowp float;
	
			varying float alpha;
	
			void main() {
				vec2 cxy = gl_PointCoord * 2.0 - 1.0;
				float radius = dot( cxy, cxy );
				gl_FragColor = vec4( vec3( alpha * max( 0.0, 1.0 - radius * radius ) ), 1.0 );
			}
		</script>

        <link href="//db.onlinewebfonts.com/c/3c9a33e9913448d684afff5b4b0cc59c?family=SCE-PS3+Rodin+LATIN" rel="stylesheet" type="text/css"/>

	</head>
	<body>
    <div id="xmb-contain">
        <div id="xmb">
            <ul>
                <li class="column" v-for="column,index in columns" v-bind:class="{active:column.active}" v-bind:style="{left:column.position.x+'px'}">
                    <div class="horizontal_cell cell" v-bind:class="{active:column.active}" v-on:click="highlightCell(column.index,0)"><img v-bind:src="'./styles/default/images/menu_bar/' + column.icon + '.png'" />
                            <label>{{column.title}}</label>
                    </div>
                    <ul>
                        <li class="cell submenu" v-for="item,itemIndex in column.items" v-bind:class="{active:item.active}" v-bind:style="{top:item.position.y+'px'}" v-on:click="highlightCell(column.index,itemIndex)">
                            <a><img class="cell_icon" v-bind:class="{active:item.active}" v-bind:src="'./styles/default/images/menu_bar/' + item.icon + '.png'" />
                                <label class="cellLabel" v-bind:class="{active:item.active, glow:item.active}">{{item.title}}
                                    <label class="subtitle" v-bind:class="{active:item.active}">{{item.subtitle}}</label>
                                </label>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <div id="cellSubParentContainer">
            <p>aaaaa</p>
        </div>
    </div>

    <!-- Clock bar -->
    <div class="XClockBar XClockBarBorder">
        <div class="XBarInner">
            <div class="grid-container">
                <div class="icon_bar">
                    <img height="24" src="./styles/default/images/cross_bar/refresh.png"/>

                </div>
                <div class="clock_bar"><div id="intro"><h1>{{ timestamp }}</h1></div></div>
                <div class="empty_bar"></div>
                <div class="notification_bar"><div class='marquee'></div></div>
            </div>


        </div>

    </div>

    <!-- overlay related -->
    <div id="overlayConfirm"></div>



	</body>

    <!-- Waves engine -->
    <script>
        const regl = createREGL( {
            attributes: {
                antialias: true
            },
            optionalExtensions: [ "EXT_disjoint_timer_query" ],
            profile: true
        } );

        const RESOLUTION = 256;
        const NUM_PARTICLES = 1600;
        const NUM_VERTICES = RESOLUTION * RESOLUTION + ( RESOLUTION + 2 ) * ( RESOLUTION - 2 ) + 10;
        const PARTICLE_SIZE = 3;

        function makeFlowVertices() {
            const vertices = new Float32Array( NUM_VERTICES * 3 );
            const yPos = new Float32Array( RESOLUTION );
            for( y = 0; y < RESOLUTION; y++ ) {
                yPos[ y ] = y / ( RESOLUTION - 1 ) * 2 - 1;
            }
            var xPos1 = -1;
            var numVertices = 0;
            for( x = 1; x < RESOLUTION; x++ ) {
                const xPos2 = x / ( RESOLUTION - 1 ) * 2 - 1;
                vertices[ numVertices++ ] = xPos2;
                vertices[ numVertices++ ] = -1;
                for( y = 0; y < RESOLUTION; y++ ) {
                    vertices[ numVertices++ ] = xPos2;
                    vertices[ numVertices++ ] = yPos[ y ];
                    vertices[ numVertices++ ] = xPos1;
                    vertices[ numVertices++ ] = yPos[ y ];
                }
                vertices[ numVertices++ ] = xPos1;
                vertices[ numVertices++ ] = 1;
                xPos1 = xPos2;
            }
            return vertices;
        }

        function makeParticleSeeds() {
            const seeds = new Float32Array( NUM_PARTICLES * 3 );
            var numSeeds = 0;
            for( var i = 0; i < NUM_PARTICLES; i++ ) {
                seeds[ numSeeds++ ] = Math.random();
                seeds[ numSeeds++ ] = Math.random();
                seeds[ numSeeds++ ] = Math.pow( Math.random(), 10 ) * PARTICLE_SIZE + 3;
            }
            return seeds;
        }

        const drawBackground = regl( {
            vert: BackgroundVertex.firstChild.nodeValue,
            frag: BackgroundFragment.firstChild.nodeValue,
            primitive: "triangle strip",
            count: 4,
            attributes: {
                position: [
                    +1, -1,
                    -1, -1,
                    +1, +1,
                    -1, +1
                ]
            },
            uniforms: {
                color: regl.prop( "color" ),
                resolution: ( context, props ) => [ context.viewportWidth, context.viewportHeight ],
                bayerTexture: regl.texture( {
                    data: Uint8Array.of(
                        0, 128,  32, 160,   8, 136,  40, 168,
                        192,  64, 224,  96, 200,  72, 232, 104,
                        48, 176,  16, 144 , 56, 184,  24, 152,
                        240, 112, 208,  80, 248, 120, 216,  88,
                        12, 140,  44, 172,   4, 132,  36, 164,
                        204,  76, 236, 108, 196,  68, 228, 100,
                        60, 188,  28, 156,  52, 180,  20, 148,
                        252, 124, 220,  92, 244, 116, 212,  84
                    ),
                    format: "alpha",
                    shape: [ 8, 8 ],
                    wrap: [ "repeat", "repeat" ]
                } )
            },
            dither: true,
            depth: { enable: true }
        } );

        const drawFlow = regl( {
            vert: FlowVertex.firstChild.nodeValue,
            frag: FlowFragment.firstChild.nodeValue,
            primitive: "triangle strip",
            count: NUM_VERTICES,
            attributes: {
                position: makeFlowVertices()
            },
            uniforms: {
                time: regl.prop("time"),
                opacity: regl.prop( "opacity" ),
                ratio: regl.prop( "ratio" ),
                step: 2 / RESOLUTION
            },
            blend: {
                enable: true,
                func: { src: 1, dst: 1 }
            },
            dither: false
        } );

        const drawParticles = regl( {
            vert: ParticleVertex.firstChild.nodeValue,
            frag: ParticleFragment.firstChild.nodeValue,
            primitive: "points",
            count: NUM_PARTICLES,
            attributes: {
                seed: makeParticleSeeds()
            },
            uniforms: {
                time: regl.prop("time"),
                ratio: regl.prop("ratio"),
                opacity: regl.prop( "particleOpacity" )
            },
            blend: {
                enable: true,
                func: { src: 1, dst: 1 }
            },
            dither: false,
            depth: { enable: false }
        } );

        const drawParams = {
            time: 0,
            color: [ 0, 0, 0 ]
        };

        const config = new Configuration( document.body );
        config.addList( "Color", [
            [ "silk"     , [ 104, 107, 108 ] ],
            [ "turquoise", [  26, 115, 115 ] ],
            [ "emerald"  , [  20, 101,  50 ] ],
            [ "sapphire" , [  37,  89, 179 ] ],
            [ "gold"     , [ 160, 120,  0 ] ],
            [ "ruby"     , [ 116,  15,  48 ] ],
            [ "amethyst" , [ 118,   6, 135 ] ],
            [ "amber"    , [ 192, 114,  40 ] ]
        ], "sapphire", ( color ) => drawParams.backgroundColor = color );
        config.addRange( "Speed", 0.25, 0, 1, 0.01, ( flowSpeed ) => drawParams.flowSpeed = flowSpeed );
        config.addRange( "Opacity", .0, 0, 1, 0.01, ( opacity ) => drawParams.opacity = opacity );
        config.addRange( "Day", 0.0, 0, 1, 0.01, ( day ) => drawParams.brightness = ( 1 - Math.cos( day * 2 * Math.PI ) ) / 1.75 );
        config.addRange( "Particle opacity", 0.0, 0, 1, 0.01, ( particleOpacity ) => drawParams.particleOpacity = particleOpacity );

        var lastTime = 0;

        var tick = regl.frame( ( context ) => {
            drawParams.backgroundColor.forEach( ( channel, i ) => drawParams.color[ i ] = channel * drawParams.brightness / 255 );
            drawParams.ratio = Math.max( 1.0, Math.min( context.viewportWidth / context.viewportHeight, 2.0 ) ) * 0.375;
            drawParams.time = drawParams.time + ( context.time - lastTime ) * drawParams.flowSpeed;
            lastTime = context.time;
            drawBackground( drawParams );
            drawFlow( drawParams );
            drawParticles( drawParams );
        } );
        //requestAnimationFrame(tick.cancel);

        function perf() {
            const count = drawFlow.stats.count / 1000;
            console.log( `background cpu: ${Math.round(drawBackground.stats.cpuTime / count)}` );
            console.log( `background gpu: ${Math.round(drawBackground.stats.gpuTime / count)}` );
            console.log( `flow       cpu: ${Math.round(drawFlow.stats.cpuTime / count)}` );
            console.log( `flow       gpu: ${Math.round(drawFlow.stats.gpuTime / count)}` );
            console.log( `particles  cpu: ${Math.round(drawParticles.stats.cpuTime / count)}` );
            console.log( `particles  gpu: ${Math.round(drawParticles.stats.gpuTime / count)}` );
        }

        function startup() {

        }
    </script>
    <!-- Waves engine end -->
<script>

    var JoyCon = {};
    function loadConfigFile() {
        $.getJSON('./config/config.json', function(data) {
            //HERE YOu need to get the name server
            JoyCon = data;
            console.debug(data);
        });
    }

    // Slide out notification / clock bar
    function showClockBar() {
        setTimeout(function(){$(".XClockBar").animate({right: "-5px",}, {duration: 1000, easing: "swing"}); }, 1000);
    }
    function hideClockBar() {
        setTimeout(function(){$(".XClockBar").animate({right: "-380px",}, {duration: 1000, easing: "swing"}); }, 1000);
    }

    $(document).ready(function() {

        // Slide out notification / clock bar when the page is ready
        showClockBar();
        // Load configuration file
        loadConfigFile();

        // List all available gamepads
        function canGame() {
            return "getGamepads" in navigator;
        }

        if(canGame()) {

            $(window).on("gamepadconnected", function() {
                hasGP = true;
                displayMessage("Wireless Dualshock 4 Controller has been connected!");
                console.log("connection event");
                repGP = window.setInterval(reportOnGamepad,1);
            });

            $(window).on("gamepaddisconnected", function() {
                displayMessage("Wireless Dualshock 4 Controller has been disconnected!");
                window.clearInterval(repGP);
            });

            //setup an interval for Chrome
            var checkGP = window.setInterval(function() {
                console.log('checkGP');
                if(navigator.getGamepads()[0]) {
                    if(!hasGP) $(window).trigger("gamepadconnected");
                    window.clearInterval(checkGP);
                }
            }, 500);
        }


    });


    function reportOnGamepad() {
        var gp = navigator.getGamepads()[0];

        // Control mapping.
        // TODO: Fetch configuration from JSON

        if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.up)].pressed)
            xmbVue.handleKey('y', 1);
        else if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.down)].pressed)
            xmbVue.handleKey('y', -1);
        else if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.left)].pressed)
            xmbVue.handleKey('x', -1);
        else if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.right)].pressed)
            xmbVue.handleKey('x', 1);
        else if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.enter)].pressed)
            xmbVue.handleKey('enter', 1);
        else if (navigator.getGamepads()[0].buttons[parseInt(JoyCon.controller.cancel)].pressed)
            xmbVue.handleKey('cancel', 1);
    }


    /*Downloaded from https://www.codeseek.co/fenwick/xmb-ripoff-ozmpqb */
    var UPPER_ICON_SIZE = 150;
    var ICON_SIZE = 100;
    var PADDING = 10;
    var menuClickSound = document.createElement("audio");
    var menuEnterSound = document.createElement("audio");
    var menuCancelSound = document.createElement("audio");
    menuClickSound.src = "./styles/default/sounds/menu_move.wav";
    menuEnterSound.src = "./styles/default/sounds/menu_enter.wav";
    menuCancelSound.src = "./styles/default/sounds/menu_cancel.wav";

    var menu_enabled = true;


    //todo: pull model from XHR or some

    var model = {
        cursor: {
            x: 0,
            y: 0
        },
        columns: {
            "system": {
                index: 0,
                title: "system",
                selectedIndex: 0,
                active: false,
                icon: "users",
                items:
                    [
                        {
                            title: "About Information",
                            subtitle: "Shows available information about the system and WebXMB Interface itself",
                            active: false,
                            icon: "info"
                        },
                        {
                            title: "Restart WebXMB+",
                            subtitle: "Restarts an WebXMB+ Interface",
                            active: false,
                            icon: "info"
                        },
                        {
                            title: "Quit WebXMB+",
                            subtitle: "Closes WebXMB+ Interface",
                            active: false,
                            icon: "info"
                        },
                    ]
            },
            "settings": {
                index: 1,
                title: "settings",
                selectedIndex: 0,
                active: false,
                icon: "settings",
                items:
                    [
                        {
                            title: "System Settings",
                            subtitle: "Adjusts settings for WebXMB Interface",
                            active: false,
                            icon: "settings/video_settings"
                        },
                        {
                            title: "Theme Settings",
                            subtitle: "Adjusts theme settings for WebXMB Interface",
                            active: false,
                            icon: "settings/theme_settings"
                        },
                        {
                            title: "Date and Time Settings",
                            subtitle: "Adjusts date and time settings for WebXMB Interface",
                            active: false,
                            icon: "settings/datetime_settings"
                        },
                        {
                            title: "Joystick Settings",
                            subtitle: "Adjusts gamepad settings for WebXMB Interface",
                            active: false,
                            icon: "settings/gamepad_settings"
                        },
                        {
                            title: "Audio Settings",
                            subtitle: "Adjusts date and time settings for WebXMB Interface",
                            active: false,
                            icon: "settings/audio_settings"
                        },
                        {
                            title: "Video Settings",
                            subtitle: "Adjusts date and time settings for WebXMB Interface",
                            active: false,
                            icon: "settings/video_settings"
                        },
                        {
                            title: "Database Settings",
                            subtitle: "Adjusts date and time settings for WebXMB Interface",
                            active: false,
                            icon: "info"
                        },
                    ]
            },
            "music": {
                index: 2,
                title: "music",
                selectedIndex: 1,
                active: false,
                icon: "music",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
            "photos": {
                index: 3,
                title: "photos",
                selectedIndex: 1,
                active: false,
                icon: "photos",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
            "videos": {
                index: 4,
                title: "videos",
                selectedIndex: 1,
                active: false,
                icon: "video",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
            "tv": {
                index: 5,
                title: "tv services",
                selectedIndex: 1,
                active: false,
                icon: "video_services",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
            "network": {
                index: 6,
                title: "network",
                selectedIndex: 1,
                active: false,
                icon: "network",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
            "games": {
                index: 7,
                title: "games",
                selectedIndex: 1,
                active: false,
                icon: "games",
                items: [{ title: "face", subtitle: "subtitle", active: false, icon: "face" }, { title: "favorite", subtitle: "subtitle", active: false, icon: "favorite" }]
            },
        }

        //add zero position to each column and item
    };_.each(model.columns, function (c) {
        c.position = { x: 0, y: 0 };
        _.each(c.items, function (i) {
            i.position = {
                x: 0,
                y: 0
            };
        });
    });
    var hasGP = false;
    var repGP;

    var xmbVue = new Vue({

        el: "#xmb",
        data: model,
        methods: {
            handleKey: function handleKey(dir, val) {
                if(menu_enabled) {
                if(dir == 'enter')
                    this.enterMenu('menu');
                else if(dir == 'cancel')
                    menuCancelSound.play();
                else {
                    this.cursor[dir] += val;

                    var nCols = this.nColumns;
                    this.cursor.x = this.cursor.x % nCols;
                    var nRows = this.nRows;
                    this.cursor.y = this.cursor.y % nRows;

                    if (this.cursor.x < 0) {
                        this.cursor.x = this.cursor.x + nCols;
                    }

                    //wrap y
                    if (this.cursor.y < 0) {
                        this.cursor.y = this.cursor.y + nRows;
                    }
                    var curStep = this.cursor.x;
                    this.highlightCell(this.cursor.x, this.cursor.y);
                }

                }
            },
            enterMenu: function enterCell(menu) {
                menu_enabled = false; // Disable main XMB Menu
                $('.horizontal_cell:not(.active)').fadeTo('fast', 0.05); // Slowly fade away Horizontal XMB Elements
                setTimeout(function(){
                    $("#xmb").animate({
                        left: "-200px",
                    }, {
                        duration: 250,
                        easing: "swing"
                    }); }, 50);
                $('.cellLabel:not(.active)').fadeOut();
                menuEnterSound.play(); // Play enter sound

            },
            exitMenu: function exitCell(menu) {
                menu_enabled = true; // Disable main XMB Menu
                $('.horizontal_cell:not(.active)').fadeTo('fast', 0.45); // Slowly fade in Horizontal XMB Elements
                setTimeout(function(){
                    $("#xmb").animate({
                        left: "0px",
                    }, {
                        duration: 250,
                        easing: "swing"
                    }); }, 50);
                $('.cellLabel:not(.active)').fadeIn();
                menuCancelSound.play();

            },
            highlightCell: function highlightCell(column, row) {

                    menuClickSound.play();


                    //update position of elements as well
                    var xAccum = (-column - 1) * (UPPER_ICON_SIZE + PADDING);
                    if (column == 0) {
                        xAccum += UPPER_ICON_SIZE + PADDING;
                    }
                    var yAccum;

                    _.each(this.columns, function (col, colKey) {
                        col.active = false;
                        yAccum = -(ICON_SIZE + PADDING) * (row + 1);

                        col.position.x = xAccum;
                        xAccum += UPPER_ICON_SIZE + PADDING;
                        if (column === col.index || column === col.index + 1) {
                            xAccum += ICON_SIZE / 2;
                        }

                        _.each(col.items, function (item, rowN) {
                            if (rowN == row && col.index == column) {
                                item.active = true;
                                col.active = true;
                            } else {
                                item.active = false;
                            }

                            if (rowN == row) {
                                yAccum += ICON_SIZE + PADDING;
                            }
                            yAccum += ICON_SIZE + PADDING;
                            item.position.y = yAccum;
                        });
                    });
                    this.cursor.y = row;
                    this.cursor.x = column;
                }

        },
        watch: {
            cursor: function cursor(e) {
                console.log('cursor mutated', e);
            }
        },
        computed: {
            nColumns: function nColumns() {
                return Object.keys(this.columns).length;
            },
            nRows: function nRows() {
                //get the row at the current index
                var row = this.columnsArray[this.cursor.x];
                if (!row) {
                    console.log('invalid row index: ', this.cursor.x);
                    return 0;
                }
                return row.items.length; //todo: number of columns in this row
            },
            columnsArray: function columnsArray() {
                var _this = this;

                //get columns in an array
                var arr = [];
                Object.keys(this.columns).forEach(function (key) {
                    arr.push(_this.columns[key]);
                });
                return _.sortBy(arr, 'index');
            }
        },
        created: function created() {
            _.each(this.columns, function (column) {
                _.each(column.items, function (item) {
                    item.active = false;
                });
            });
            this.highlightCell(this.cursor.x, this.cursor.y);
        }
    });

    // handle movement based on keys
    $('body').on('keyup', function (e) {

        if (e.key == "ArrowUp") {
            xmbVue.handleKey('y', -1);
        } else if (e.key == "ArrowDown") {

            xmbVue.handleKey('y', 1);
        } else if (e.key == "ArrowLeft") {

            xmbVue.handleKey('x', -1);
        } else if (e.key == "ArrowRight") {

            xmbVue.handleKey('x', 1);
        } else if (e.key == "Enter") {
            xmbVue.enterMenu('enter');
        } else if (e.key == "Backspace") {
            e.preventDefault();
            xmbVue.exitMenu('cancel');
        }

    });
    $(document).on("keydown", function (event) {
// Chrome & Firefox || Internet Explorer
        if (document.activeElement === document.body || document.activeElement === document.body.parentElement) {
            // SPACE (32) o BACKSPACE (8)
            if (event.keyCode === 32 || event.keyCode === 8) {
                event.preventDefault();
            }
        }});
    $('body').on("mousewheel", _.throttle(scrollHandler, 10));

    function scrollHandler(e) {
        if (e.deltaX) {
            xmbVue.handleKey('x', Math.sign(e.deltaX));
        }
        if (e.deltaY) {
            xmbVue.handleKey('y', Math.sign(e.deltaY));
        }
    }

    // Notification related
    function displayMessage(text) {
        $('.marquee').html(text);
        // Incrase Bar height to fit notification text.
        $(".XClockBar").animate({
            height: "80px",
        }, {
            duration: 500,
            easing: "swing"
        });
        $('.marquee').fadeIn("slow");
        $('.marquee').bind('finished', function(){
            $('.marquee').fadeOut("slow");
            $(this).marquee('destroy');
            setTimeout(function(){

                //Load new content using Ajax and update the marquee container
                $(".XClockBar").animate({
                    height: "40px",
                }, {
                    duration: 600,
                    easing: "swing"
                });

            }, 1000);
                //Change text to something else after first loop finishes

            }).marquee({
            //duration in milliseconds of the marquee
            duration: 5000,
            //gap in pixels between the tickers
            gap: 100,
            //time in milliseconds before the marquee will start animating
            delayBeforeStart: 1500,
            //'left' or 'right'
            direction: 'left',
            //true or false - should the marquee be duplicated to show an effect of continues flow
            duplicated: false
        });
    }

    // Clock related
    var vue_det = new Vue({
        el: '#intro',
        data: {
            timestamp: ""
        },
        created() {
            setInterval(this.getNow, 1000);
        },
        methods: {
            getNow: function() {
                const today = new Date();
                const date = (today.getMonth()+1)+'/'+today.getDate();
                const time = today.getHours() + ":" + (today.getMinutes()<10?'0':'') + today.getMinutes() + ":" + (today.getSeconds()<10?'0':'') + today.getSeconds();
                const dateTime = date +' '+ time;
                this.timestamp = dateTime;
            }
        }
    });

</script>
</html>
<?php
class SystemInfo
{

    /**
     * Return RAM Total in Bytes.
     *
     * @return int Bytes
     */
    public function getRamTotal()
    {
        $result = 0;
        if (PHP_OS == 'WINNT') {
            $lines = null;
            $matches = null;
            exec('wmic ComputerSystem get TotalPhysicalMemory /Value', $lines);
            if (preg_match('/^TotalPhysicalMemory\=(\d+)$/', $lines[2], $matches)) {
                $result = $matches[1];
            }
        } else {
            $fh = fopen('/proc/meminfo', 'r');
            while ($line = fgets($fh)) {
                $pieces = array();
                if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $pieces)) {
                    $result = $pieces[1];
                    // KB to Bytes
                    $result = $result * 1024;
                    break;
                }
            }
            fclose($fh);
        }
        // KB RAM Total
        return (int) $result;
    }
}
$system = new SystemInfo();
echo "RAM total: " . round($system->getRamTotal() / 1024 / 1024) . " MB \n";
?>