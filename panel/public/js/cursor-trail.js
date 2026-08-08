// Decorative cursor trail: a small number of spring-linked "worms" that
// chase the mouse and are drawn additively for a soft glowing trail.
// Vanilla JS, no build step (this repo ships public/ assets unbundled -
// see public/css/public.css's own comment on why). Public site only
// (client request 2026-08-08: removed from the admin panel) - included
// via resources/views/layouts/public.blade.php. Configurable via
// window.zcCursorTrail* globals set before this loads - color, worm
// count, spring stiffness (speed), and line width - kept generic in case
// a future page wants a different look, not because two surfaces use it
// today.
//
// Skipped entirely when the user prefers reduced motion, or when the
// primary input has no real pointer (touch-only devices) - a mouse trail
// makes no sense to draw and never fires there anyway.
(function () {
    "use strict";

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        return;
    }

    if (!window.matchMedia("(pointer: fine)").matches) {
        return;
    }

    var WORM_COUNT = window.zcCursorTrailWormCount || 12;
    var NODE_COUNT = window.zcCursorTrailNodeCount || 20;
    var SPRING = window.zcCursorTrailSpring || 0.42;
    var LINE_WIDTH = window.zcCursorTrailLineWidth || 1;
    var color = window.zcCursorTrailColor || "#38bdf8";

    var canvas = document.createElement("canvas");
    canvas.style.position = "fixed";
    canvas.style.inset = "0";
    canvas.style.width = "100%";
    canvas.style.height = "100%";
    canvas.style.zIndex = "2147483647";
    canvas.style.pointerEvents = "none";
    document.body.appendChild(canvas);

    var ctx = canvas.getContext("2d");
    var dpr = window.devicePixelRatio || 1;
    var pointer = { x: window.innerWidth / 2, y: window.innerHeight / 2 };
    var active = false;
    var frameId = null;

    function resize() {
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function Node(x, y) {
        this.x = x;
        this.y = y;
        this.vx = 0;
        this.vy = 0;
    }

    // Each worm is a chain of nodes; the head chases the pointer, and each
    // following node chases the node ahead of it, all under simple spring
    // + friction physics - this is what produces the trailing, elastic
    // "rope" look rather than every node moving in lockstep.
    function Worm(spring) {
        this.spring = spring;
        this.friction = 0.55;
        this.nodes = [];
        for (var i = 0; i < NODE_COUNT; i++) {
            this.nodes.push(new Node(pointer.x, pointer.y));
        }
    }

    Worm.prototype.update = function () {
        var spring = this.spring;
        var head = this.nodes[0];
        head.vx += (pointer.x - head.x) * spring;
        head.vy += (pointer.y - head.y) * spring;

        for (var i = 0; i < this.nodes.length; i++) {
            var node = this.nodes[i];
            if (i > 0) {
                var prev = this.nodes[i - 1];
                node.vx += (prev.x - node.x) * spring;
                node.vy += (prev.y - node.y) * spring;
            }
            node.vx *= this.friction;
            node.vy *= this.friction;
            node.x += node.vx;
            node.y += node.vy;
        }
    };

    Worm.prototype.draw = function () {
        ctx.beginPath();
        ctx.moveTo(this.nodes[0].x, this.nodes[0].y);
        for (var i = 1; i < this.nodes.length; i++) {
            ctx.lineTo(this.nodes[i].x, this.nodes[i].y);
        }
        ctx.stroke();
    };

    var worms = [];

    function spawnWorms() {
        worms = [];
        for (var i = 0; i < WORM_COUNT; i++) {
            worms.push(new Worm(SPRING + (i / WORM_COUNT) * (SPRING * 0.07)));
        }
    }

    function render() {
        if (!active) {
            return;
        }
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.globalCompositeOperation = "lighter";
        ctx.strokeStyle = color;
        ctx.lineWidth = LINE_WIDTH;
        for (var i = 0; i < worms.length; i++) {
            worms[i].update();
            worms[i].draw();
        }
        frameId = window.requestAnimationFrame(render);
    }

    function move(e) {
        pointer.x = e.clientX;
        pointer.y = e.clientY;
        if (!active) {
            active = true;
            spawnWorms();
            render();
        }
    }

    function stop() {
        active = false;
        if (frameId !== null) {
            window.cancelAnimationFrame(frameId);
            frameId = null;
        }
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    resize();
    window.addEventListener("resize", resize);
    document.addEventListener("mousemove", move);
    document.addEventListener("mouseleave", stop);
    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            stop();
        }
    });
})();
