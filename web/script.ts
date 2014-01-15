module PrettyPrinter {

    function wrapNode(node:Node, inline:boolean = true):HTMLElement {
        var wrapper = document.createElement('div');
        wrapper.style.margin = '0.25em';
        wrapper.style.display = inline ? 'inline-block' : 'block';
        wrapper.style.verticalAlign = 'middle';
        wrapper.appendChild(node);
        return wrapper;
    }

    function block(node:Node):Node {
        var div = document.createElement('div');
        div.appendChild(node);
        return div;
    }

    function wrap(text:string):HTMLElement {
        return wrapNode(document.createTextNode(text));
    }

    function italics(text:string) {
        var wrapped = wrap(text);
        wrapped.style.fontStyle = 'italic';
        return wrapped;
    }

    function collect(nodes:Node[]):Node {
        var x = document.createDocumentFragment();
        for (var i = 0; i < nodes.length; i++) {
            x.appendChild(nodes[i]);
        }
        return x;
    }

    function expandable(headContent:Node, content:() => Node, inline:boolean = true):Node {
        var container = document.createElement('div');
        var head = document.createElement('div');
        head.style.backgroundColor = '#eee';
        head.style.cursor = 'pointer';
        head.addEventListener('mouseenter', function () {
            head.style.backgroundColor = '#ddd';
        });
        head.addEventListener('mouseleave', function () {
            head.style.backgroundColor = '#eee';
        });
        head.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        head.appendChild(headContent);
        container.appendChild(head);
        container.style.backgroundColor = '#fff';
        var body = document.createElement('div');
        body.style.borderTopWidth = '0.125em';
        body.style.borderTopStyle = 'dashed';
        body.style.borderTopColor = '#888';
        var open = false;

        head.addEventListener('click', function () {
            if (open) {
                body.innerHTML = '';
                container.removeChild(body);
            } else {
                body.appendChild(content());
                container.appendChild(body);
            }

            open = !open;
        });

        return wrapNode(container, inline);
    }

    function createTable(data:Node[][]):HTMLTableElement {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var x = 0; x < data.length; x++) {
            var row = document.createElement('tr');
            table.appendChild(row);
            for (var y = 0; y < data[x].length; y++) {
                var td = document.createElement('td');
                td.style.padding = '0';
                td.appendChild(data[x][y]);
                row.appendChild(td);
            }
        }

        return table;
    }

    function bold(content:string):Node {
        var box = wrap(content);
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = wrap(word);
        box.style.color = '#008';
        box.style.fontWeight = 'bold';
        return box;
    }

    interface ValueObjectProperty extends ValueVariable {
        className:string;
        access:string;
    }

    interface ValueArrayEntry {
        key:Value;
        value:Value;
    }

    interface ValueVisitor<T> {
        visitString: (x:string) => T;
        visitBool: (x:boolean) => T;
        visitNull: () => T;
        visitUnknown: () => T;
        visitInt: (x:number) => T;
        visitFloat: (x:number) => T;
        visitResource: (type:string, id:number) => T;
        visitObject: (x:ValueObject) => T;
        visitArray: (entries:ValueArrayEntry[]) => T;
        visitException: (x:ValueException) => T;
    }

    interface ValueObject {
        hash: string;
        className: string;
        properties:ValueObjectProperty[];
    }

    interface ValueExceptionLocation {
        file: string;
        line: number;
        source: {
            [x:number]: string;
        };
    }

    interface ValueStaticProperty extends ValueObjectProperty {
    }

    interface ValueStaticVariable extends ValueVariable {
        className: string;
        functionName: string;
    }

    interface ValueVariable {
        name: string;
        value: Value;
    }

    interface ValueExceptionGlobals {
        staticProperties: ValueStaticProperty[];
        staticVariables: ValueStaticVariable[];
        globalVariables: ValueVariable[];
    }

    interface ValueException {
        className: string;
        code: string;
        message: string;
        location: ValueExceptionLocation;
        locals: ValueVariable[];
        globals: ValueExceptionGlobals;
        stack: ValueExceptionStackFrame[];
        previous: ValueException;
    }

    interface ValueExceptionStackFrame {
        object: ValueObject;
        className: string;
        isStatic: boolean;
        functionName: string;
        location: ValueExceptionLocation;
        args: Value[];
    }

    interface Value {
        acceptVisitor<T> (x:ValueVisitor<T>): T;
    }

    function parse(json:string):Value {
        var root = JSON.parse(json);

        function parseLocation(x:any):ValueExceptionLocation {
            if (x === null)
                return null;

            return {
                file:   x['file'],
                line:   x['line'],
                source: x['sourceCode']
            };
        }

        function parseException(e:any):ValueException {
            if (e === null)
                return null;

            var locals:any[] = e['locals'];
            var staticProps:any[] = e['globals']['staticProperties'];
            var staticVars:any[] = e['globals']['staticVariables'];
            var globalVars:any[] = e['globals']['globalVariables'];
            var stack:any[] = e['stack'];

            return {
                className: e['class'],
                message:   e['message'],
                code:      e['code'],
                location:  parseLocation(e['location']),
                locals:    locals.map(function (x) {
                    return {
                        name:  x['name'],
                        value: parseValue(x['value'])
                    };
                }),
                globals:   {
                    staticProperties: staticProps.map(function (x) {
                        return {
                            name:      x['name'],
                            value:     parseValue(x['value']),
                            className: x['class'],
                            access:    x['access']
                        };
                    }),
                    staticVariables:  staticVars.map(function (x) {
                        return {
                            name:         x['name'],
                            value:        parseValue(x['value']),
                            className:    x['class'],
                            functionName: x['function']
                        };
                    }),
                    globalVariables:  globalVars.map(function (x) {
                        return {
                            name:  x['name'],
                            value: parseValue(x['value'])
                        };
                    })
                },
                stack:     stack.map(function (x) {
                    var args:any[] = x['args'];
                    return {
                        object:       parseObject(x['object']),
                        className:    x['class'],
                        isStatic:     x['isStatic'],
                        functionName: x['function'],
                        args:         args.map(parseValue),
                        location:     parseLocation(x['location'])
                    };
                }),
                previous:  parseException(e['previous'])
            };
        }

        function parseObject(x):ValueObject {
            if (x === null)
                return null;

            var object = root['objects'][ x[1]];
            var objectProps:any[] = object['properties'];

            return {
                hash:       object['hash'],
                className:  object['class'],
                properties: objectProps.map(function (x) {
                    return {
                        name:      x['name'],
                        value:     parseValue(x['value']),
                        className: x['class'],
                        access:    x['access']
                    };
                })
            };
        }

        function parseValue(x:any):Value {
            return {
                acceptVisitor: function <T> (v:ValueVisitor<T>):T {
                    if (typeof x === 'string')
                        return v.visitString(x);

                    if (typeof x === 'number')
                        if (x % 1 === 0)
                            return v.visitInt(x);
                        else
                            return v.visitFloat(x);

                    if (typeof x === 'boolean')
                        return v.visitBool(x);

                    if (x === null)
                        return v.visitNull();

                    if (x[0] === 'float')
                        if (x[1] === 'inf' || x[1] === '+inf')
                            return v.visitFloat(Infinity);
                        else if (x[1] === '-inf')
                            return v.visitFloat(-Infinity);
                        else if (x[1] === 'nan')
                            return v.visitFloat(NaN);
                        else
                            return v.visitFloat(x[1]);

                    if (x[0] === 'array') {
                        var arrayEntries:any[] = root['arrays'][x[1]]['entries'];

                        return v.visitArray(arrayEntries.map(function (x) {
                            return {
                                key:   parseValue(x[0]),
                                value: parseValue(x[1])
                            };
                        }));
                    }

                    if (x[0] === 'unknown')
                        return v.visitUnknown();

                    if (x[0] === 'object') {
                        return v.visitObject(parseObject(x));
                    }

                    if (x[0] === 'exception')
                        return v.visitException(parseException(x[1]));

                    if (x[0] === 'resource')
                        return v.visitResource(x[1]['type'], x[1]['id']);

                    throw { message: "not goord" };
                }
            };
        }

        return parseValue(root['root']);
    }

    function render(v:Value):Node {
        function renderArray(entries:ValueArrayEntry[]):Node {
            return expandable(keyword('array'), function () {
                if (entries.length == 0)
                    return italics('empty');

                return createTable(entries.map(function (x) {
                    return [
                        render(x.key),
                        wrap('=>'),
                        render(x.value)
                    ];
                }));
            });
        }

        function renderObject(object:ValueObject):Node {
            return expandable(collect([keyword('new'), wrap(object.className)]), function () {
                return createTable(object.properties.map(function (property) {
                    var variable = renderVariable(property.name);
                    var value = render(property.value);
                    return [
                        collect([keyword(property.access), variable]),
                        wrap('='),
                        value
                    ];
                }));
            });
        }

        function renderStack(stack:ValueExceptionStackFrame[]):Node {
            return expandable(bold('stack trace'), function () {
                var rows:Node[][] = [];

                for (var x = 0; x < stack.length; x++) {
                    var container = document.createDocumentFragment();
                    var div1 = document.createElement('div');
                    div1.appendChild(wrap('#' + String(x + 1)));
                    div1.appendChild(renderLocation(stack[x].location));
                    container.appendChild(div1);

                    var div2 = document.createElement('div');
                    div2.style.marginLeft = '4em';
                    div2.style.marginBottom = '1em';
                    div2.appendChild(renderFunctionCall(stack[x]));
                    container.appendChild(div2);

                    rows.push([container]);
                }

                rows.push([collect([
                    wrap('#' + String(x + 1)),
                    expandable(wrap('{main}'), function () {
                        return italics('n/a');
                    })
                ])]);

                return createTable(rows);
            }, false);
        }

        function renderFunctionCall(call:ValueExceptionStackFrame):Node {
            var result = document.createDocumentFragment();
            var prefix = '';
            if (call.object) {
                result.appendChild(renderObject(call.object));
                prefix += '->';
            } else if (call.className) {
                prefix += call.className;
                prefix += call.isStatic ? '::' : '->';
            }

            result.appendChild(wrap(prefix + call.functionName + '('));

            for (var i = 0; i < call.args.length; i++) {
                if (i != 0)
                    result.appendChild(wrap(','));

                result.appendChild(render(call.args[i]));
            }

            result.appendChild(wrap(')'));

            return result;
        }

        function renderVariable(name:string):Node {
            function red(v:string) {
                var result = wrap(v);
                result.style.color = '#800';
                return result;
            }

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name)) {
                return red('$' + name);
            } else {
                return collect([red('$' + '{'), renderString(name), red('}')])
            }
        }

        function renderLocals(locals:ValueVariable[]):Node {
            return expandable(bold('local variables'), function () {
                if (!(locals instanceof Array))
                    return italics('n/a');

                if (locals.length == 0)
                    return italics('none');

                return createTable(locals.map(function (local) {
                    return [
                        renderVariable(local.name),
                        wrap('='),
                        render(local.value)
                    ];
                }));
            }, false);
        }

        function renderGlobals(globals:ValueExceptionGlobals) {
            return expandable(bold('global variables'), function () {
                if (!globals)
                    return italics('n/a');

                var staticVariables = globals.staticVariables;
                var staticProperties = globals.staticProperties;
                var globalVariables = globals.globalVariables;
                var rows = [];

                for (var i = 0; i < staticVariables.length; i++) {
                    var v = staticVariables[i];
                    var s:string = '';

                    if (v.className)
                        s += v.className + '::';

                    s += v.functionName + '()::';

                    rows.push([
                        collect([wrap(s), keyword('static'), renderVariable(v.name)]),
                        wrap('='),
                        render(v.value)
                    ]);
                }

                for (var i = 0; i < staticProperties.length; i++) {
                    var p = staticProperties[i];
                    var pieces = document.createDocumentFragment();
                    pieces.appendChild(wrap(p.className + '::'));
                    pieces.appendChild(keyword(p.access));
                    pieces.appendChild(keyword('static'));
                    pieces.appendChild(renderVariable(p.name));

                    rows.push([pieces, wrap('='), render(p.value)]);
                }

                for (var i = 0; i < globalVariables.length; i++) {
                    var pieces = document.createDocumentFragment();
                    var v2 = globalVariables[i];
                    var superglobals = ['_SERVER', '_GET', '_POST', '_FILES', '_REQUEST', '_COOKIE', '_ENV', '_SESSION'];
                    if (superglobals.indexOf(v2.name) == -1)
                        pieces.appendChild(keyword('global'));
                    pieces.appendChild(renderVariable(v2.name));

                    rows.push([pieces, wrap('='), render(v2.value)]);

                }

                return createTable(rows);
            }, false);
        }

        function renderException(x:ValueException):Node {
            if (!x)
                return italics('none');

            return expandable(collect([keyword('new'), wrap(x.className)]), function () {
                var table = createTable([
                    [bold('code'), wrap(x.code)],
                    [bold('message'), wrap(x.message)],
                    [bold('location'), renderLocation(x.location)],
                    [bold('previous'), renderException(x.previous)]
                ]);
                return collect([
                    table,
                    block(renderLocals(x.locals)),
                    block(renderStack(x.stack)),
                    block(renderGlobals(x.globals))
                ]);
            });
        }

        function renderLocation(location:ValueExceptionLocation):Node {
            var wrapper = document.createDocumentFragment();
            var file = location.file;
            var line = location.line;
            wrapper.appendChild(wrap(file));
            wrapper.appendChild(renderInt(line));

            return expandable(wrapper, function () {
                var sourceCode = location.source;

                if (!sourceCode)
                    return italics('n/a');

                var codeLines = document.createDocumentFragment();

                for (var codeLine in sourceCode) {
                    if (!sourceCode.hasOwnProperty(codeLine))
                        continue;

                    var lineNumber = document.createElement('span');
                    lineNumber.appendChild(document.createTextNode(String(codeLine)));
                    lineNumber.style.width = '3em';
                    lineNumber.style.borderRightWidth = '0.125em';
                    lineNumber.style.borderRightStyle = 'solid';
                    lineNumber.style.borderRightColor = 'black';
                    lineNumber.style.display = 'inline-block';

                    var row = document.createElement('div');
                    row.appendChild(lineNumber);
                    row.appendChild(document.createTextNode(sourceCode[codeLine]));
                    if (codeLine == line)
                        row.style.backgroundColor = '#fbb';

                    codeLines.appendChild(row);
                }

                var inner = wrapNode(codeLines, false);
                inner.style.backgroundColor = '#def';
                inner.style.padding = '0.25em';
                return inner;
            });
        }

        function renderString(x:string):Node {
            function renderString2(x:string):{
                length:number;
                result:Node;
            } {
                var result = document.createElement('span');
                result.style.color = '#080';
                result.style.backgroundColor = '#dFd';
                result.style.fontWeight = 'bold';
                result.style.display = 'inline';

                var translate = {
                    '\\': '\\\\',
                    '$':  '\\$',
                    '\r': '\\r',
                    '\v': '\\v',
                    '\f': '\\f',
                    '"':  '\\"'
                };

                var length = 0;

                var buffer:string = '"';

                function flush() {
                    if (buffer.length > 0)
                        result.appendChild(document.createTextNode(buffer));

                    buffer = "";
                }

                for (var i = 0; i < x.length; i++) {
                    var char:string = x.charAt(i);
                    var code:number = x.charCodeAt(i);

                    function escaped(x:string):Node {
                        var box = document.createElement('span');
                        box.appendChild(document.createTextNode(x));
                        box.style.color = '#008';
                        box.style.fontWeight = 'bold';
                        return box;
                    }

                    if (translate[char] !== undefined) {
                        flush();
                        result.appendChild(escaped(translate[char]));
                        length += 2;
                    } else if ((code >= 32 && code <= 126) || char === '\n' || char === '\t') {
                        buffer += char;
                        length += 1;
                    } else {
                        flush();
                        result.appendChild(escaped('\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16))));
                        length += 4;
                    }
                }

                buffer += '"';

                flush();

                return { result: wrapNode(result), length: length };
            }

            var threshold = 200;

            if (x.length > threshold) {
                return expandable(keyword('string'), function () {
                    return renderString2(x).result;
                });
            }

            var result = renderString2(x);

            if (result.length > threshold) {
                return expandable(keyword('string'), function () {
                    return result.result;
                });
            }

            return result.result;
        }

        function renderInt(x:number):Node {
            var result = wrap(String(x));
            result.style.color = '#00F';
            return result;
        }

        return v.acceptVisitor({
            visitString:    renderString,
            visitBool:      function (x:boolean) {
                return keyword(x ? 'true' : 'false');
            },
            visitNull:      function () {
                return keyword('null');
            },
            visitUnknown:   function () {
                return bold('unknown type');
            },
            visitInt:       renderInt,
            visitFloat:     function (x:number):Node {
                var str = x % 1 == 0 ? String(x) + '.0' : String(x);
                var result = wrap(str);
                result.style.color = '#00F';
                return result;
            },
            visitResource:  function (type:string) {
                return collect([keyword('resource'), wrap(type)]);
            },
            visitObject:    renderObject,
            visitArray:     renderArray,
            visitException: renderException
        });
    }

    function start() {
        var body = document.getElementsByTagName('body')[0];
        var text = document.createElement('textarea');
        body.appendChild(text);
        text.style.width = '800px';
        text.style.height = '500px';
        var container = document.createElement('div');
        body.appendChild(container);

        function onchange() {
            var parsedJSON = JSON.parse(text.value);
            text.value = JSON.stringify(parsedJSON, undefined, 4);
            var rendered:Node = render(parse(text.value));
            container.innerHTML = '';
            container.appendChild(rendered);
        }

        text.addEventListener('change', onchange);

        text.value = "{\"root\":[\"exception\",{\"class\":\"MuhMockException\",\"code\":\"Dummy exception code\",\"message\":\"This is a dummy exception message.\\n\\nlololool\",\"location\":{\"line\":9000,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"previous\":null,\"stack\":[{\"function\":\"aFunction\",\"class\":\"DummyClass1\",\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":[\"object\",0],\"args\":[[\"object\",1]]},{\"function\":\"aFunction\",\"class\":null,\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":null,\"args\":[[\"object\",2]]}],\"locals\":[{\"name\":\"lol\",\"value\":8},{\"name\":\"foo\",\"value\":\"bar\"}],\"globals\":{\"staticProperties\":[{\"name\":\"blahProperty\",\"value\":null,\"class\":\"BlahClass\",\"access\":\"private\",\"isDefault\":false}],\"globalVariables\":[{\"name\":\"lol global\",\"value\":null},{\"name\":\"blahVariable\",\"value\":null}],\"staticVariables\":[{\"name\":\"public\",\"value\":null,\"class\":null,\"function\":\"BlahAnotherClass\"},{\"name\":\"lolStatic\",\"value\":null,\"class\":\"BlahYetAnotherClass\",\"function\":\"blahMethod\"}]}}],\"arrays\":[],\"objects\":[{\"class\":\"ErrorHandler\\\\DummyClass1\",\"hash\":\"0000000058b5388000000000367cf886\",\"properties\":[{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388300000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388a00000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]}]}";
        onchange();
    }

    document.addEventListener('DOMContentLoaded', start);
}
