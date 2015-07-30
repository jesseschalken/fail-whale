module FailWhale {

    function rescroll(document:Document) {
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

        export interface Resource {
            type: string;
            id: number;
        }

        export interface Value {
            type: string;
            exception: Exception;
            object: number;
            array: number;
            string: number;
            int: number;
            float: number;
            resource: Resource;
        }

        export interface Location {
            file: string;
            line: number;
            source: {[lineNo:number]: string};
        }

        export interface ValueVisitor<T> {
            visitString:    (id:number) => T;
            visitArray:     (id:number) => T;
            visitObject:    (id:number) => T;
            visitInt:       (val:number) => T;
            visitTrue:      () => T;
            visitFalse:     () => T;
            visitNull:      () => T;
            visitPosInf:    () => T;
            visitNegInf:    () => T;
            visitNaN:       () => T;
            visitUnknown:   () => T;
            visitFloat:     (val:number) => T;
            visitResource:  (val:Resource) => T;
            visitException: (val:Exception) => T;
        }

        export function visit<T>(x:Value, f:ValueVisitor<T>):T {
            switch (x.type) {
                case Data.Type.INT:
                    return f.visitInt(x.int);
                case Data.Type.FLOAT:
                    return f.visitFloat(x.float);
                case Data.Type.TRUE:
                    return f.visitTrue();
                case Data.Type.FALSE:
                    return f.visitFalse();
                case Data.Type.STRING:
                    return f.visitString(x.string);
                case Data.Type.POS_INF:
                    return f.visitPosInf();
                case Data.Type.NEG_INF:
                    return f.visitNegInf();
                case Data.Type.NAN:
                    return f.visitNaN();
                case Data.Type.ARRAY:
                    return f.visitArray(x.array);
                case Data.Type.OBJECT:
                    return f.visitObject(x.object);
                case Data.Type.EXCEPTION:
                    return f.visitException(x.exception);
                case Data.Type.RESOURCE:
                    return f.visitResource(x.resource);
                case Data.Type.NULL:
                    return f.visitNull();
                case Data.Type.UNKNOWN:
                    return f.visitUnknown();
                default:
                    throw "unknown type " + x.type;
            }
        }
    }

    class Renderer implements Data.ValueVisitor<Node> {
        private root:Data.Root;
        private document:Document;
        private padding = '3px';

        constructor(root:Data.Root, document:Document) {
            this.root = root;
            this.document = document;
        }

        private plain(content:string, inline:boolean = true):HTMLElement {
            var span = this.document.createElement(inline ? 'span' : 'div');
            span.appendChild(this.document.createTextNode(content));
            return span;
        }

        private italics(t:string):Node {
            var wrapped = this.plain(t);
            wrapped.style.display = 'inline';
            wrapped.style.fontStyle = 'italic';
            return wrapped;
        }

        private notice(t:string):Node {
            var wrapped = this.plain(t);
            wrapped.style.fontStyle = 'italic';
            wrapped.style.padding = this.padding;
            wrapped.style.display = 'inline-block';
            return wrapped;
        }

        private collect(nodes:Node[]):Node {
            var x = this.document.createDocumentFragment();
            for (var i = 0; i < nodes.length; i++)
                x.appendChild(nodes[i]);
            return x;
        }

        private expandable(content:{
            head:Node;
            body:() => Node;
            open:boolean;
            inline?:boolean;
        }):Node {
            var container = this.document.createElement('div');
            var inline = content.inline;
            if (inline === undefined)
                inline = true;
            if (inline)
                container.style.display = 'inline-table';

            var head = this.document.createElement('div');
            head.style.backgroundColor = '#eee';
            head.style.cursor = 'pointer';
            head.style.padding = this.padding;
            head.addEventListener('mouseenter', () => {
                head.style.backgroundColor = '#ddd';
                body.style.borderColor = '#ddd';
            });
            head.addEventListener('mouseleave', () => {
                head.style.backgroundColor = '#eee';
                body.style.borderColor = '#eee';
            });
            head.addEventListener('mousedown', (e) => {
                e.preventDefault();
            });
            head.appendChild(content.head);
            container.appendChild(head);

            var body = this.document.createElement('div');
            body.style.borderSpacing = '0';
            body.style.padding = '0';
            body.style.backgroundColor = 'white';
            body.style.borderColor = '#eee';
            body.style.borderWidth = '1px';
            body.style.borderTopWidth = '0';
            body.style.borderStyle = 'solid';
            container.appendChild(body);

            var open = content.open;

            var refresh = () => {
                body.innerHTML = '';

                if (open) {
                    body.appendChild(content.body());
                }

                body.style.display = open ? 'block' : 'none';
            };

            refresh();

            head.addEventListener('click', () => {
                var scroll = rescroll(this.document);
                open = !open;
                refresh();
                scroll();
            });

            return container;
        }

        private table(data:Node[][]):Node {
            var table = this.document.createElement('table');
            table.style.borderSpacing = '0';
            table.style.padding = '0';

            for (var i = 0; i < data.length; i++) {
                var tr = this.document.createElement('tr');
                table.appendChild(tr);
                for (var j = 0; j < data[i].length; j++) {
                    var td = this.document.createElement('td');
                    td.style.padding = this.padding;
                    td.style.verticalAlign = 'baseline';
                    td.appendChild(data[i][j]);
                    tr.appendChild(td);
                }
            }

            return table;
        }

        private bold(content:string):Node {
            var box = this.plain(content);
            box.style.fontWeight = 'bold';
            return box;
        }

        private keyword(word:string) {
            var box = this.plain(word);
            box.style.color = '#009';
            box.style.fontWeight = 'bold';
            return box;
        }

        renderRoot() {
            var container = this.document.createElement('div');
            container.style.whiteSpace = 'pre';
            container.style.fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
            container.style.fontSize = "10pt";
            container.style.lineHeight = '16px';
            container.appendChild(this.renderValue(this.root.root));
            return container;
        }

        private renderValue(value:Data.Value):Node {
            return Data.visit(value, this);
        }

        visitInt(val:number) {
            return this.renderNumber(String(val));
        }

        visitFloat(val:number) {
            var str = String(val);
            str = val % 1 == 0 ? str + '.0' : str;
            return this.renderNumber(str);
        }

        visitTrue() {
            return this.keyword('true');
        }

        visitFalse() {
            return this.keyword('false');
        }

        visitString(id:number) {
            return this.renderString(this.root.strings[id]);
        }

        visitPosInf() {
            return this.renderNumber('INF');
        }

        visitNegInf() {
            return this.renderNumber('-INF');
        }

        visitNaN() {
            return this.renderNumber('NAN');
        }

        visitArray(id:number) {
            return this.renderArray(id);
        }

        visitObject(id:number) {
            return this.renderObject(this.root.objects[id]);
        }

        visitException(val:Data.Exception) {
            return this.renderException(val);
        }

        visitResource(val:Data.Resource) {
            return this.collect([this.keyword('resource'), this.plain(' ' + val.type)]);
        }

        visitNull() {
            return this.keyword('null');
        }

        visitUnknown() {
            var span = this.plain('unknown type');
            span.style.fontStyle = 'italic';
            return span;
        }

        private renderArray(id:number):Node {
            var array = this.root.arrays[id];
            return this.expandable({
                head: this.keyword('array'),
                body: () => {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return this.notice('empty');

                    var container = this.document.createDocumentFragment();
                    container.appendChild(this.table(array.entries.map((x) => {
                        return [
                            this.renderValue(x.key),
                            this.plain('=>'),
                            this.renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(this.notice(array.entriesMissing + " entries missing..."));

                    return container;
                },
                open: false
            });
        }

        private renderObject(object:Data.Object1):Node {
            return this.expandable({
                head: this.collect([this.keyword('new'), this.plain(' ' + object.className)]),
                body: () => {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return this.notice('empty');

                    var container = this.document.createDocumentFragment();
                    container.appendChild(this.table(object.properties.map((property) => {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';

                        return [
                            this.collect([
                                this.keyword(property.access),
                                this.plain(' ' + prefix),
                                this.renderVariable(property.name)
                            ]),
                            this.plain('='),
                            this.renderValue(property.value)
                        ];
                    })));

                    if (object.propertiesMissing > 0)
                        container.appendChild(this.notice(object.propertiesMissing + " properties missing..."));

                    return container;
                },
                open: false
            });
        }

        private renderStack(stack:Data.Stack[], missing:number):Node {
            var renderFunctionCall = (call:Data.Stack):Node => {
                var result = this.document.createDocumentFragment();
                var prefix = '';
                if (call.object) {
                    var object = this.root.objects[call.object];
                    result.appendChild(this.renderObject(object));
                    prefix += '->';
                    if (object.className !== call.className)
                        prefix += call.className + '::';
                } else if (call.className) {
                    prefix += call.className;
                    prefix += call.isStatic ? '::' : '->';
                }

                result.appendChild(this.plain(prefix + call.functionName));

                if (call.args instanceof Array) {
                    if (call.args.length == 0 && call.argsMissing == 0) {
                        result.appendChild(this.plain('()'));
                    } else {
                        result.appendChild(this.plain('( '));

                        for (var i = 0; i < call.args.length; i++) {
                            if (i != 0)
                                result.appendChild(this.plain(', '));

                            var arg = call.args[i];
                            if (arg.name) {
                                if (arg.typeHint) {
                                    var typeHint:Node;
                                    switch (arg.typeHint) {
                                        case 'array':
                                        case 'callable':
                                            typeHint = this.keyword(arg.typeHint);
                                            break;
                                        default:
                                            typeHint = this.plain(arg.typeHint);
                                    }
                                    result.appendChild(typeHint);
                                    result.appendChild(this.plain(' '));
                                }
                                if (arg.isReference) {
                                    result.appendChild(this.plain('&'));
                                }
                                result.appendChild(this.renderVariable(arg.name));
                                result.appendChild(this.plain(' = '));
                            }

                            result.appendChild(this.renderValue(arg.value));
                        }

                        if (call.argsMissing > 0) {
                            if (i != 0)
                                result.appendChild(this.plain(', '));
                            result.appendChild(this.italics(call.argsMissing + ' arguments missing...'));
                        }

                        result.appendChild(this.plain(' )'));
                    }
                } else {
                    result.appendChild(this.plain('( '));
                    result.appendChild(this.italics('not available'));
                    result.appendChild(this.plain(' )'));
                }

                return result;
            };

            var rows:Node[][] = [];

            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    this.plain('#' + String(x + 1)),
                    this.renderLocation(stack[x].location),
                    renderFunctionCall(stack[x])
                ]);
            }

            if (missing == 0) {
                rows.push([
                    this.plain('#' + String(x + 1)),
                    this.expandable({
                        head: this.plain('{main}'),
                        body: () => {
                            return this.notice('no source code');
                        },
                        open: false
                    }),
                    this.collect([])
                ]);
            }

            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(rows));
            if (missing > 0)
                container.appendChild(this.notice(missing + " stack frames missing..."));

            return container;
        }

        private renderVariable(name:string):Node {
            var red = (v:string) => {
                var result = this.plain(v);
                result.style.color = '#700';
                return result;
            };

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return this.collect([red('$' + '{'), this.renderString({bytes: name, bytesMissing: 0}), red('}')])
        }

        private renderLocals(locals:Data.Variable[], missing:number):Node {
            if (!(locals instanceof Array))
                return this.notice('not available');

            if (locals.length == 0 && missing == 0)
                return this.notice('none');

            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(locals.map((local) => {
                return [
                    this.renderVariable(local.name),
                    this.plain('='),
                    this.renderValue(local.value)
                ];
            })));

            if (missing > 0)
                container.appendChild(this.notice(missing + " variables missing..."));

            return container;
        }

        private renderGlobals(globals:Data.Globals) {
            if (!globals)
                return this.notice('not available');

            var staticVariables = globals.staticVariables;
            var staticProperties = globals.staticProperties;
            var globalVariables = globals.globalVariables;
            var rows:Node[][] = [];

            for (var i = 0; i < globalVariables.length; i++) {
                var pieces = this.document.createDocumentFragment();
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
                    pieces.appendChild(this.keyword('global'));
                    pieces.appendChild(this.plain(' '));
                }
                pieces.appendChild(this.renderVariable(v2.name));

                rows.push([pieces, this.plain('='), this.renderValue(v2.value)]);

            }

            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = this.document.createDocumentFragment();
                pieces.appendChild(this.keyword(p.access));
                pieces.appendChild(this.plain(' '));
                pieces.appendChild(this.plain(p.className + '::'));
                pieces.appendChild(this.renderVariable(p.name));

                rows.push([pieces, this.plain('='), this.renderValue(p.value)]);
            }

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = this.document.createDocumentFragment();
                pieces.appendChild(this.keyword('function'));
                pieces.appendChild(this.plain(' '));

                if (v.className)
                    pieces.appendChild(this.plain(v.className + '::'));

                pieces.appendChild(this.plain(v.functionName + '()::'));
                pieces.appendChild(this.renderVariable(v.name));

                rows.push([
                    pieces,
                    this.plain('='),
                    this.renderValue(v.value)
                ]);
            }

            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(rows));

            var block = (node:Node):Node => {
                var div = this.document.createElement('div');
                div.appendChild(node);
                return div;
            };

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(this.notice(globals.staticPropertiesMissing + " static properties missing...")));

            if (globals.globalVariablesMissing > 0)
                container.appendChild(block(this.notice(globals.globalVariablesMissing + " global variables missing...")));

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(this.notice(globals.staticPropertiesMissing + " static variables missing...")));

            return container;
        }

        private renderException(x:Data.Exception):Node {
            if (!x)
                return this.italics('none');

            return this.expandable({
                head: this.collect([this.keyword('exception'), this.plain(' ' + x.className)]),
                body: () => {

                    var body = this.document.createElement('div');
                    body.appendChild(this.expandable({
                        inline: false,
                        open:   true,
                        head:   this.bold('exception'),
                        body:   () => {
                            return this.table([
                                [this.bold('code'), this.plain(x.code)],
                                [this.bold('message'), this.plain(x.message)],
                                [this.bold('location'), this.renderLocation(x.location, true)],
                                [this.bold('previous'), this.renderException(x.previous)]
                            ]);
                        }
                    }));
                    body.appendChild(this.expandable({
                        inline: false,
                        open:   true,
                        head:   this.bold('locals'),
                        body:   () => {
                            return this.renderLocals(x.locals, x.localsMissing);
                        }
                    }));
                    body.appendChild(this.expandable({
                        inline: false,
                        open:   true,
                        head:   this.bold('stack'),
                        body:   () => {
                            return this.renderStack(x.stack, x.stackMissing);
                        }
                    }));
                    body.appendChild(this.expandable({
                        inline: false,
                        open:   true,
                        head:   this.bold('globals'),
                        body:   () => {
                            return this.renderGlobals(x.globals);
                        }
                    }));
                    body.style.padding = this.padding;
                    return body;
                },
                open: true
            });
        }

        private renderLocation(location:Data.Location, open:boolean = false):Node {
            return this.expandable({
                head: location
                          ? this.collect([this.plain(location.file + ':'), this.renderNumber(String(location.line))])
                          : this.plain('[internal function]'),
                body: () => {
                    if (!location || !location.source)
                        return this.notice('no source code');

                    var padding = '4px';
                    var lineNumber = this.document.createElement('div');
                    lineNumber.style.display = 'inline-block';
                    lineNumber.style.padding = padding;
                    lineNumber.style.textAlign = 'right';
                    lineNumber.style.color = '#999';
                    lineNumber.style.backgroundColor = '#333';
                    lineNumber.style.borderRightColor = '#666';
                    lineNumber.style.borderRightWidth = '1px';
                    lineNumber.style.borderRightStyle = 'dashed';
                    lineNumber.style.verticalAlign = 'top';
                    lineNumber.style.minWidth = '32px';

                    var code = this.document.createElement('div');
                    code.style.display = 'inline-block';
                    code.style.padding = padding;
                    code.style.width = '800px';
                    code.style.overflowX = 'auto';
                    code.style.backgroundColor = '#222';
                    code.style.color = '#ccc';
                    code.style.verticalAlign = 'top';
                    var codeDiv = this.document.createElement('div');
                    code.appendChild(codeDiv);
                    codeDiv.style.display = 'inline-block';
                    codeDiv.style.minWidth = '100%';

                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;

                        var lineDiv = this.plain(decodeUTF8(location.source[codeLine]) + "\n", false);
                        if (codeLine == location.line) {
                            lineDiv.style.backgroundColor = '#f88';
                            lineDiv.style.color = '#300';
                            lineDiv.style.borderRadius = padding;
                        }
                        lineNumber.appendChild(this.plain(String(codeLine) + "\n", false));
                        codeDiv.appendChild(lineDiv);
                    }

                    return this.collect([lineNumber, code]);
                },
                open: open
            });
        }

        private renderString(x:Data.String1):Node {
            var doRender = ():Node => {
                var span = this.document.createElement('span');
                span.style.color = '#080';
                span.style.fontWeight = 'bold';

                var translate:{[index:string]: string} = {
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
                            span.appendChild(this.plain(buffer));

                        buffer = "";
                        span.appendChild(this.keyword(escaped));
                    } else {
                        buffer += char;
                    }
                }

                span.appendChild(this.plain(buffer + '"'));

                var container = this.document.createElement('div');
                container.style.display = 'inline-table';
                container.appendChild(span);

                if (x.bytesMissing > 0) {
                    container.appendChild(this.plain(' '));
                    container.appendChild(this.italics(x.bytesMissing + ' bytes missing...'));
                }

                return container;
            };

            var visualLength = 0;

            for (var i = 0; i < x.bytes.length; i++) {
                var code = x.bytes.charCodeAt(i);
                var isPrintable = code >= 32 && code <= 126;
                visualLength += isPrintable ? 1 : 4;
            }

            var numLines = x.bytes.split("\n").length;
            if (visualLength > 200 || numLines > 20)
                return this.expandable({open: false, head: this.keyword('string'), body: doRender});
            else
                return doRender();
        }

        private renderNumber(x:string):Node {
            var result = this.plain(x);
            result.style.color = '#00f';
            return result;
        }
    }

    export function renderJSON(json:string, document:HTMLDocument):Node {
        var root:Data.Root = JSON.parse(json);

        return new Renderer(root, document).renderRoot();
    }

    function decodeUTF8(utf8Bytes:string):string {
        return decodeURIComponent(escape(utf8Bytes));
    }
}

declare function escape(s:string):string;
