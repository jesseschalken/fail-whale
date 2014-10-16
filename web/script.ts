module FailWhale {

    function rescroll() {
        function view() {
            return {
                x: document.documentElement.scrollLeft || document.body.scrollLeft,
                y: document.documentElement.scrollTop || document.body.scrollTop,
                w: document.documentElement.clientWidth || window.innerWidth,
                h: document.documentElement.clientHeight || window.innerHeight
            };
        }

        function doc() {
            return {
                w: document.documentElement.scrollWidth || document.body.scrollWidth,
                h: document.documentElement.scrollHeight || document.body.scrollHeight
            };
        }

        var doc1 = doc();
        var view1 = view();

        return function () {
            var doc2 = doc();
            var view2 = view();

            window.scrollTo(
                view1.x + view1.w >= doc1.w && view1.x != 0 ? doc2.w - view2.w : view2.x,
                view1.y + view1.h >= doc1.h && view1.y != 0 ? doc2.h - view2.h : view2.y
            );
        };
    }

    module Data {
        export var Type = {
            STRING:    'string',
            ARRAY:     'array',
            OBJECT:    'object',
            INT:       'int',
            TRUE:      'true',
            FALSE:     'false',
            NULL:      'null',
            POS_INF:   '+inf',
            NEG_INF:   '-inf',
            NAN:       'nan',
            UNKNOWN:   'unknown',
            FLOAT:     'float',
            RESOURCE:  'resource',
            EXCEPTION: 'exception'
        };

        export interface Root {
            root: Value;
            strings: String1[];
            objects: Object1[];
            arrays: Array1[];
        }
        export interface String1 {
            bytes: string;
            bytesMissing: number
        }
        export interface Array1 {
            entriesMissing: number;
            entries: {
                key: Value;
                value: Value;
            }[];
        }
        export interface Object1 {
            hash: string;
            className: string;
            properties: Property[];
            propertiesMissing: number;
        }
        export interface Variable {
            name: string;
            value: Value;
        }
        export interface Property extends Variable {
            className: string;
            access: string;
        }
        export interface StaticVariable extends Variable {
            className: string;
            functionName: string;
        }
        export interface Globals {
            staticProperties: Property[];
            staticPropertiesMissing: number;
            staticVariables: StaticVariable[];
            staticVariablesMissing: number;
            globalVariables: Variable[];
            globalVariablesMissing: number;
        }
        export interface Exception {
            locals: Variable[];
            localsMissing: number;
            globals:Globals;
            stack: Stack[];
            stackMissing: number;
            className: string;
            code: string;
            message: string;
            location: Location;
            previous: Exception;
        }
        export interface Stack {
            functionName: string;
            args: {
                value: Value;
                name: string;
                typeHint: string;
                isReference: boolean;
            }[];
            argsMissing: number;
            object: number;
            className: string;
            isStatic: boolean;
            location: Location;
        }
        export interface Value {
            type: string;
            exception: Exception;
            object: number;
            array: number;
            string: number;
            int: number;
            float: number;
            resource: {
                type: string;
                id: number;
            };
        }
        export interface Location {
            file: string;
            line: number;
            source: {[lineNo:number]: string};
        }
    }

    var padding = '3px';

    function plain(content:string, inline:boolean = true):HTMLElement {
        var span = document.createElement(inline ? 'span' : 'div');
        span.appendChild(document.createTextNode(content));
        return span;
    }

    function italics(t:string):Node {
        var wrapped = plain(t);
        wrapped.style.display = 'inline';
        wrapped.style.fontStyle = 'italic';
        return wrapped;
    }

    function notice(t:string):Node {
        var wrapped = plain(t);
        wrapped.style.fontStyle = 'italic';
        wrapped.style.padding = padding;
        wrapped.style.display = 'inline-block';
        return wrapped;
    }

    function collect(nodes:Node[]):Node {
        var x = document.createDocumentFragment();
        for (var i = 0; i < nodes.length; i++)
            x.appendChild(nodes[i]);
        return x;
    }

    function expandable(content:{
        head:Node;
        body:() => Node;
        open:boolean;
        inline?:boolean;
    }):Node {
        var container = document.createElement('div');
        var inline = content.inline;
        if (inline === undefined)
            inline = true;
        if (inline)
            container.style.display = 'inline-table';

        var head = document.createElement('div');
        head.style.backgroundColor = '#eee';
        head.style.cursor = 'pointer';
        head.style.padding = padding;
        head.addEventListener('mouseenter', function () {
            head.style.backgroundColor = '#ddd';
            body.style.borderColor = '#ddd';
        });
        head.addEventListener('mouseleave', function () {
            head.style.backgroundColor = '#eee';
            body.style.borderColor = '#eee';
        });
        head.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        head.appendChild(content.head);
        container.appendChild(head);

        var body = document.createElement('div');
        body.style.borderSpacing = '0';
        body.style.padding = '0';
        body.style.backgroundColor = 'white';
        body.style.borderColor = '#eee';
        body.style.borderWidth = '1px';
        body.style.borderTopWidth = '0';
        body.style.borderStyle = 'solid';
        container.appendChild(body);

        var open = content.open;

        function refresh() {
            body.innerHTML = '';

            if (open) {
                body.appendChild(content.body());
            }

            body.style.display = open ? 'block' : 'none';
        }

        refresh();

        head.addEventListener('click', function () {
            var scroll = rescroll();
            open = !open;
            refresh();
            scroll.call();
        });

        return container;
    }

    function table(data:Node[][]):Node {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var i = 0; i < data.length; i++) {
            var tr = document.createElement('tr');
            table.appendChild(tr);
            for (var j = 0; j < data[i].length; j++) {
                var td = document.createElement('td');
                td.style.padding = padding;
                td.style.verticalAlign = 'baseline';
                td.appendChild(data[i][j]);
                tr.appendChild(td);
            }
        }

        return table;
    }

    function bold(content:string):Node {
        var box = plain(content);
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = plain(word);
        box.style.color = '#009';
        box.style.fontWeight = 'bold';
        return box;
    }

    export function renderJSON(json:string):Node {
        var root:Data.Root = JSON.parse(json);

        var container = document.createElement('div');
        container.style.whiteSpace = 'pre';
        container.style.fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
        container.style.fontSize = "10pt";
        container.style.lineHeight = '16px';
        container.appendChild(renderValue(root.root));
        return container;

        function renderValue(x:Data.Value) {
            switch (x.type) {
                case Data.Type.INT:
                    return renderNumber(String(x.int));
                case Data.Type.FLOAT:
                    var str = String(x.float);
                    str = x.float % 1 == 0 ? str + '.0' : str;
                    return renderNumber(str);
                case Data.Type.TRUE:
                    return keyword('true');
                case Data.Type.FALSE:
                    return keyword('false');
                case Data.Type.STRING:
                    return renderString(root.strings[x.string]);
                case Data.Type.POS_INF:
                    return renderNumber('INF');
                case Data.Type.NEG_INF:
                    return renderNumber('-INF');
                case Data.Type.NAN:
                    return renderNumber('NAN');
                case Data.Type.ARRAY:
                    return renderArray(x.array);
                case Data.Type.OBJECT:
                    return renderObject(root.objects[x.object]);
                case Data.Type.EXCEPTION:
                    return renderException(x.exception);
                case Data.Type.RESOURCE:
                    return collect([keyword('resource'), plain(' ' + x.resource.type)]);
                case Data.Type.NULL:
                    return keyword('null');
                case Data.Type.UNKNOWN:
                    var span = plain('unknown type');
                    span.style.fontStyle = 'italic';
                    return span;
                default:
                    throw "unknown type " + x.type;
            }
        }

        function renderArray(id:number):Node {
            var array = root.arrays[id];
            return expandable({
                head: keyword('array'),
                body: function () {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(table(array.entries.map(function (x) {
                        return [
                            renderValue(x.key),
                            plain('=>'),
                            renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(notice(array.entriesMissing + " entries missing..."));

                    return container;
                },
                open: false
            });
        }

        function renderObject(object:Data.Object1):Node {
            return expandable({
                head: collect([keyword('new'), plain(' ' + object.className)]),
                body: function () {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(table(object.properties.map(function (property) {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';

                        return [
                            collect([
                                keyword(property.access),
                                plain(' ' + prefix),
                                renderVariable(property.name)
                            ]),
                            plain('='),
                            renderValue(property.value)
                        ];
                    })));

                    if (object.propertiesMissing > 0)
                        container.appendChild(notice(object.propertiesMissing + " properties missing..."));

                    return container;
                },
                open: false
            });
        }

        function renderStack(stack:Data.Stack[], missing:number):Node {
            function renderFunctionCall(call:Data.Stack):Node {
                var result = document.createDocumentFragment();
                var prefix = '';
                if (call.object) {
                    var object = root.objects[call.object];
                    result.appendChild(renderObject(object));
                    prefix += '->';
                    if (object.className !== call.className)
                        prefix += call.className + '::';
                } else if (call.className) {
                    prefix += call.className;
                    prefix += call.isStatic ? '::' : '->';
                }

                result.appendChild(plain(prefix + call.functionName));

                if (call.args instanceof Array) {
                    if (call.args.length == 0 && call.argsMissing == 0) {
                        result.appendChild(plain('()'));
                    } else {
                        result.appendChild(plain('( '));

                        for (var i = 0; i < call.args.length; i++) {
                            if (i != 0)
                                result.appendChild(plain(', '));

                            var arg = call.args[i];
                            if (arg.name) {
                                if (arg.typeHint) {
                                    var typeHint:Node;
                                    switch (arg.typeHint) {
                                        case 'array':
                                        case 'callable':
                                            typeHint = keyword(arg.typeHint);
                                            break;
                                        default:
                                            typeHint = plain(arg.typeHint);
                                    }
                                    result.appendChild(typeHint);
                                    result.appendChild(plain(' '));
                                }
                                if (arg.isReference) {
                                    result.appendChild(plain('&'));
                                }
                                result.appendChild(renderVariable(arg.name));
                                result.appendChild(plain(' = '));
                            }

                            result.appendChild(renderValue(arg.value));
                        }

                        if (call.argsMissing > 0) {
                            if (i != 0)
                                result.appendChild(plain(', '));
                            result.appendChild(italics(call.argsMissing + ' arguments missing...'));
                        }

                        result.appendChild(plain(' )'));
                    }
                } else {
                    result.appendChild(plain('( '));
                    result.appendChild(italics('not available'));
                    result.appendChild(plain(' )'));
                }

                return result;
            }

            var rows:Node[][] = [];

            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    plain('#' + String(x + 1)),
                    renderLocation(stack[x].location),
                    renderFunctionCall(stack[x])
                ]);
            }

            if (missing == 0) {
                rows.push([
                    plain('#' + String(x + 1)),
                    expandable({
                        head: plain('{main}'),
                        body: function () {
                            return notice('no source code');
                        },
                        open: false
                    }),
                    collect([])
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(table(rows));
            if (missing > 0)
                container.appendChild(notice(missing + " stack frames missing..."));

            return container;
        }

        function renderVariable(name:string):Node {
            function red(v:string) {
                var result = plain(v);
                result.style.color = '#700';
                return result;
            }

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return collect([red('$' + '{'), renderString({bytes: name, bytesMissing: 0}), red('}')])
        }

        function renderLocals(locals:Data.Variable[], missing:number):Node {
            if (!(locals instanceof Array))
                return notice('not available');

            if (locals.length == 0 && missing == 0)
                return notice('none');

            var container = document.createDocumentFragment();
            container.appendChild(table(locals.map(function (local) {
                return [
                    renderVariable(local.name),
                    plain('='),
                    renderValue(local.value)
                ];
            })));

            if (missing > 0)
                container.appendChild(notice(missing + " variables missing..."));

            return container;
        }

        function renderGlobals(globals:Data.Globals) {
            if (!globals)
                return notice('not available');

            var staticVariables = globals.staticVariables;
            var staticProperties = globals.staticProperties;
            var globalVariables = globals.globalVariables;
            var rows = [];

            for (var i = 0; i < globalVariables.length; i++) {
                var pieces = document.createDocumentFragment();
                var v2 = globalVariables[i];
                var superGlobals = [
                    'GLOBALS',
                    '_SERVER',
                    '_GET',
                    '_POST',
                    '_FILES',
                    '_COOKIE',
                    '_SESSION',
                    '_REQUEST',
                    '_ENV'
                ];
                if (superGlobals.indexOf(v2.name) == -1) {
                    pieces.appendChild(keyword('global'));
                    pieces.appendChild(plain(' '));
                }
                pieces.appendChild(renderVariable(v2.name));

                rows.push([pieces, plain('='), renderValue(v2.value)]);

            }

            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(keyword(p.access));
                pieces.appendChild(plain(' '));
                pieces.appendChild(plain(p.className + '::'));
                pieces.appendChild(renderVariable(p.name));

                rows.push([pieces, plain('='), renderValue(p.value)]);
            }

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(keyword('function'));
                pieces.appendChild(plain(' '));

                if (v.className)
                    pieces.appendChild(plain(v.className + '::'));

                pieces.appendChild(plain(v.functionName + '()::'));
                pieces.appendChild(renderVariable(v.name));

                rows.push([
                    pieces,
                    plain('='),
                    renderValue(v.value)
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(table(rows));

            function block(node:Node):Node {
                var div = document.createElement('div');
                div.appendChild(node);
                return div;
            }

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(notice(globals.staticPropertiesMissing + " static properties missing...")));

            if (globals.globalVariablesMissing > 0)
                container.appendChild(block(notice(globals.globalVariablesMissing + " global variables missing...")));

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(notice(globals.staticPropertiesMissing + " static variables missing...")));

            return container;
        }

        function renderException(x:Data.Exception):Node {
            if (!x)
                return italics('none');

            return expandable({
                head: collect([keyword('exception'), plain(' ' + x.className)]),
                body: function () {

                    var body = document.createElement('div');
                    body.appendChild(expandable({
                        inline: false,
                        open:   true,
                        head:   bold('exception'),
                        body:   function () {
                            return table([
                                [bold('code'), plain(x.code)],
                                [bold('message'), plain(x.message)],
                                [bold('location'), renderLocation(x.location, true)],
                                [bold('previous'), renderException(x.previous)]
                            ]);
                        }
                    }));
                    body.appendChild(expandable({
                        inline: false,
                        open:   true,
                        head:   bold('locals'),
                        body:   function () {
                            return renderLocals(x.locals, x.localsMissing);
                        }
                    }));
                    body.appendChild(expandable({
                        inline: false,
                        open:   true,
                        head:   bold('stack'),
                        body:   function () {
                            return renderStack(x.stack, x.stackMissing);
                        }
                    }));
                    body.appendChild(expandable({
                        inline: false,
                        open:   true,
                        head:   bold('globals'),
                        body:   function () {
                            return renderGlobals(x.globals);
                        }
                    }));
                    body.style.padding = padding;
                    return body;
                },
                open: true
            });
        }

        function renderLocation(location:Data.Location, open:boolean = false):Node {
            return expandable({
                head: location
                    ? collect([plain(location.file + ':'), renderNumber(String(location.line))])
                    : plain('[internal function]'),
                body: function () {
                    if (!location || !location.source)
                        return notice('no source code');

                    var padding = '4px';
                    var lineNumber = document.createElement('div');
                    lineNumber.style.display = 'inline-block';
                    lineNumber.style.padding = padding;
                    lineNumber.style.textAlign = 'right';
                    lineNumber.style.color = '#999';
                    lineNumber.style.backgroundColor = '#333';
                    lineNumber.style.borderRightColor = '#666';
                    lineNumber.style.borderRightWidth = '1px';
                    lineNumber.style.borderRightStyle = 'dashed';
                    lineNumber.style.verticalAlign = 'top';

                    var code = document.createElement('div');
                    code.style.display = 'inline-block';
                    code.style.padding = padding;
                    code.style.width = '800px';
                    code.style.overflowX = 'auto';
                    code.style.backgroundColor = '#222';
                    code.style.color = '#ccc';
                    code.style.verticalAlign = 'top';
                    var codeDiv = document.createElement('div');
                    code.appendChild(codeDiv);
                    codeDiv.style.display = 'inline-block';
                    codeDiv.style.minWidth = '100%';

                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;

                        var lineDiv = plain(decodeUTF8(location.source[codeLine]) + "\n", false);
                        if (codeLine == location.line) {
                            lineDiv.style.backgroundColor = '#f88';
                            lineDiv.style.color = '#300';
                            lineDiv.style.borderRadius = padding;
                        }
                        lineNumber.appendChild(plain(String(codeLine) + "\n", false));
                        codeDiv.appendChild(lineDiv);
                    }

                    return collect([lineNumber, code]);
                },
                open: open
            });
        }

        function renderString(x:Data.String1):Node {
            function doRender():Node {
                var span = document.createElement('span');
                span.style.color = '#080';
                span.style.fontWeight = 'bold';

                var translate = {
                    '\\': '\\\\',
                    '$':  '\\$',
                    '\r': '\\r',
                    '\v': '\\v',
                    '\f': '\\f',
                    '"':  '\\"'
                };

                var buffer:string = '"';

                for (var i = 0; i < x.bytes.length; i++) {
                    var char:string = x.bytes.charAt(i);
                    var code:number = x.bytes.charCodeAt(i);

                    if (translate[char] !== undefined) {
                        var escaped = translate[char];
                    } else if ((code < 32 || code > 126) && char !== '\n' && char != '\t') {
                        escaped = '\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16));
                    } else {
                        escaped = undefined;
                    }

                    if (escaped !== undefined) {
                        if (buffer.length > 0)
                            span.appendChild(plain(buffer));

                        buffer = "";
                        span.appendChild(keyword(escaped));
                    } else {
                        buffer += char;
                    }
                }

                span.appendChild(plain(buffer + '"'));

                var container = document.createElement('div');
                container.style.display = 'inline-table';
                container.appendChild(span);

                if (x.bytesMissing > 0) {
                    container.appendChild(plain(' '));
                    container.appendChild(italics(x.bytesMissing + ' bytes missing...'));
                }

                return container;
            }

            var visualLength = 0;

            for (var i = 0; i < x.bytes.length; i++) {
                var code = x.bytes.charCodeAt(i);
                var isPrintable = code >= 32 && code <= 126;
                visualLength += isPrintable ? 1 : 4;
            }

            var numLines = x.bytes.split("\n").length;
            if (visualLength > 200 || numLines > 20)
                return expandable({open: false, head: keyword('string'), body: doRender});
            else
                return doRender();
        }

        function renderNumber(x:string):Node {
            var result = plain(x);
            result.style.color = '#00f';
            return result;
        }
    }

    function decodeUTF8(utf8Bytes:string):string {
        return decodeURIComponent(escape(utf8Bytes));
    }
}

declare function escape(s:string):string;
