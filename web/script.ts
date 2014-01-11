///<reference path="jquery.d.ts" />

module PrettyPrinter {
    $(function () {
        var body = $('body');
        var text = $('<textarea></textarea>').appendTo(body).css('width', 800).css('height', 500);
        var container = $('<div></div>').appendTo(body);

        function renderString(x:string, root):JQuery {
            var result = $(document.createElement('span'));
            result.text(x);
            return result;
        }

        function renderInt(x:number, root):JQuery {
            var result = $(document.createElement('span'));
            result.text(x);
            return result;
        }

        function renderFloat(x:number, root):JQuery {
            var result = $(document.createElement('span'));
            result.text(x);
            return result;
        }

        function renderBool(x:boolean, root):JQuery {
            var result = $(document.createElement('span'));
            result.text(x ? 'true' : 'false');
            return result;
        }

        function renderNull(root):JQuery {
            var result = $(document.createElement('span'));
            result.text('null');
            return result;
        }

        function renderArray(x:any, root):JQuery {
            var result = $(document.createElement('span'));
            result.text('array...');
            result.click(function (eventObject:JQueryEventObject) {
            });
            return result;
        }

        function renderUnknown() {
            var result = $(document.createElement('span'));
            result.text('unknown');
            return result;
        }

        function renderObject(x, root):JQuery {
            var result = $(document.createElement('span'));
            result.text('object...');
            return result;
        }

        function renderException(x, root):JQuery {
            var result = $(document.createElement('span'));
            result.text('exception...');
            return result;
        }

        function render(v):JQuery {
            var root = v['root'];

            if (typeof root === 'string')
                return renderString(root, v);
            else if (typeof root === 'number')
                if (root % 1 === 0)
                    return renderInt(root, v);
                else
                    return renderFloat(root, v);
            else if (typeof root === 'boolean')
                return renderBool(root, v);
            else if (root === null)
                return renderNull(v);
            else if (root[0] === 'float')
                if (root[1] === 'inf' || root[1] === '+inf')
                    return renderFloat(Infinity, v);
                else if (root[1] === '-inf')
                    return renderFloat(-Infinity, v);
                else if (root[1] === 'nan')
                    return renderFloat(NaN, v);
                else
                    return renderFloat(root[1], v);
            else if (root[0] === 'array')
                return renderArray(root[1], v);
            else if (root[0] === 'unknown')
                return renderUnknown();
            else if (root[0] === 'object')
                return renderObject(root[1], v);
            else if (root[0] === 'exception')
                return renderException(root[1], v);
            else
                throw new String("not goord");
        }

        text.change(function (e:JQueryEventObject) {
            var json:string = text.val();
            var parsedJSON = $.parseJSON(json);
            var rendered = render(parsedJSON);
            container.empty();
            container.append(rendered);
        });

        text.val("{\"root\":[\"exception\",{\"class\":\"MuhMockException\",\"code\":\"Dummy exception code\",\"message\":\"This is a dummy exception message.\\n\\nlololool\",\"location\":{\"line\":9000,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"previous\":null,\"stack\":[{\"function\":\"aFunction\",\"class\":\"DummyClass1\",\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":[\"object\",0],\"args\":[[\"object\",1]]},{\"function\":\"aFunction\",\"class\":null,\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":null,\"args\":[[\"object\",2]]}],\"locals\":[{\"name\":\"lol\",\"value\":8},{\"name\":\"foo\",\"value\":\"bar\"}],\"globals\":{\"staticProperties\":[{\"name\":\"blahProperty\",\"value\":null,\"class\":\"BlahClass\",\"access\":\"private\",\"isDefault\":false}],\"globalVariables\":[{\"name\":\"lol global\",\"value\":null},{\"name\":\"blahVariable\",\"value\":null}],\"staticVariables\":[{\"name\":\"public\",\"value\":null,\"class\":null,\"function\":\"BlahAnotherClass\"},{\"name\":\"lolStatic\",\"value\":null,\"class\":\"BlahYetAnotherClass\",\"function\":\"blahMethod\"}]}}],\"arrays\":[],\"objects\":[{\"class\":\"ErrorHandler\\\\DummyClass1\",\"hash\":\"0000000058b5388000000000367cf886\",\"properties\":[{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388300000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388a00000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]}]}");
        text.change();
    });
}