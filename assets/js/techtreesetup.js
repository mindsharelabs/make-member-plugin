//Settings
var techTree = (function(api) {
  //offsets
  api.offsets = api.offsets || {};

  //defaults
  api.settings = {
    wrapper: "#svg",
    nodeOrientation: "horizontal",
    //linkOrientation: "",
    imageRendering: "pixelated",
    useSpriteSheet: false,
    useShadows: false,
    initialLinkColor: "#707070",
    strokeColor: "#7ab50d",
    itemFill: "#bad48c"
  };

  api.dimensions = {
    svgInitialWidth: window.innerWidth,
    svgInitialHeight: window.innerHeight + 1000,
    nodeOuterWidth: 100,
    nodeOuterHeight: 100,
    nodeInnerBorder: 4,
    nodeInnerWidth: 80,
    nodeInnerHeight: 80,
    spriteSheetWidth: 512,
    spriteSheetHeight: 512,
    margin: { top: 20, right: 0, bottom: 20, left: 0 }
  };
  api.durations = {
    activateLink: 750,
    activateNode: 250
  };
  api.loadSettings = function(settings) {
    var newSettings = settings || {};
    //general settings
    newSettings.settings = newSettings.settings || {};
    Object.keys(api.settings).forEach(function(key) {
      if (newSettings.settings[key] != null) {
        api.settings[key] = newSettings.settings[key];
      }
    });
    //dimensions
    newSettings.dimensions = newSettings.dimensions || {};
    Object.keys(api.dimensions).forEach(function(key) {
      api.dimensions[key] = newSettings.dimensions[key] || api.dimensions[key];
    });
    //durations
    newSettings.durations = newSettings.durations || {};
    api.durations.activateLink =
      newSettings.durations.activateLink || api.durations.activateLink;
    api.durations.activateNode =
      newSettings.durations.activateNode || api.durations.activateNode;
  };
  return api;
})(techTree || {});


//Plot Helper
var techTree = (function(api) {
  api.orientNodes = function(d, depths) {
    switch (api.settings.nodeOrientation) {
      case "vertical":
        api.orientNodes.vertical(d, depths);
        break;
      case "horizontal":
        api.orientNodes.horizontal(d, depths);
        break;
      case "circular":
        api.orientNodes.circular(d, depths);
        break;
      default:
        api.orientNodes.vertical(d, depths);
        break;
    }
    api.orientLinks();
  };
  api.orientNodes.horizontal = function(d, depths) {
    d.x = d.depth * api.dimensions.nodeOuterHeight;
    d.y =
      (d._depthElementCount * api.dimensions.svgHeight) /
        (depths[d.depth] + 1) -
      api.dimensions.nodeInnerHeight / 2;
  };
  api.orientNodes.vertical = function(d, depths) {
    d.x =
      (d._depthElementCount * api.dimensions.svgWidth) / (depths[d.depth] + 1) -
      api.dimensions.nodeInnerWidth / 2;
    d.y = d.depth * api.dimensions.nodeOuterHeight;
  };
  api.orientNodes.circular = function(d, depths) {
    d.x =
      api.dimensions.svgWidth / 2 -
      api.dimensions.nodeInnerWidth / 2 +
      120 *
        d.depth *
        Math.cos((2 * d._depthElementCount * Math.PI) / depths[d.depth]);
    d.y =
      api.dimensions.svgHeight / 2 -
      api.dimensions.nodeInnerHeight / 2 +
      120 *
        d.depth *
        Math.sin((2 * Math.PI * d._depthElementCount) / depths[d.depth]); //d.depth * api.dimensions.nodeOuterHeight;
  };
  api.orientLinks = function() {
    switch (api.settings.nodeOrientation) {
      case "vertical":
        api.lineFunction = api.orientLinks.vertical;
        break;
      case "horizontal":
        api.lineFunction = api.orientLinks.horizontal;
        break;
      case "circular":
        api.lineFunction = api.orientLinks.horizontal;
        break;
      default:
        api.lineFunction = api.orientLinks.vertical;
        break;
    }
  };
  api.orientLinks.horizontal = d3.svg
    .diagonal()
    .source(function(d) {
      return { x: d.source.y, y: d.source.x };
    })
    .target(function(d) {
      return { x: d.target.y, y: d.target.x };
    })
    .projection(function(d) {
      return [
        d.y + api.dimensions.nodeInnerHeight / 2,
        d.x + api.dimensions.nodeInnerWidth / 2
      ];
    });
  api.orientLinks.vertical = d3.svg.diagonal().projection(function(d) {
    return [
      d.x + api.dimensions.nodeInnerWidth / 2,
      d.y + api.dimensions.nodeInnerHeight / 2
    ];
  });
  return api;
})(techTree || {});


//Event Helper
var techTree = (function(api) {
  // Toggle children on click.
  api.clickHandler = function clickHandler(d, nodesByName) {


    if (d.selected) {
      var otherSelected = true;
      if (d.requirements && d.requirements.length) {
        for (var i = 0; i < d.requirements.length; i += 1) {
          otherSelected =
            otherSelected &&
            nodesByName[d.requirements[i]].datum().selected === true;
        }
      }
      if (otherSelected) {
        d.selected = true;
        techTree.updateNode(nodesByName[d.name]);
        techTree.updateLinks(d, nodesByName);
      }
    }






    
  };
  return api;
})(techTree || {});




//Acheivement Helper
var techTree = (function(api) {
  //load Acheived Nodes
  api.loadAcheivments = function loadAcheivments(d, nodesByName) {
    console.log(nodesByName);
    for (var i = 0; i < nodesByName.length; i += 1) {
      console.log(nodesByName[i]);
    }
  };
  return api;
})(techTree || {});


//Node Helper
var techTree = (function(api) {
  var initializeBorder = function initializeBorder(container) {
    container
      .append("rect")
      .attr( "width", api.dimensions.nodeInnerWidth + 2 * api.dimensions.nodeInnerBorder )
      .attr( "height", api.dimensions.nodeInnerHeight + 2 * api.dimensions.nodeInnerBorder )
  };
  var initializeImages = function initializeImages(container) {
    var offset;
    var image = container
      .append("svg")
      .attr("viewBox", function(d) {
        return (
          "0 0 " +
          api.dimensions.nodeInnerWidth +
          " " +
          api.dimensions.nodeInnerHeight
        );
      })
      .attr("preserveAspectRatio", "xMidYMid meet")
      .attr("x", api.dimensions.nodeInnerBorder)
      .attr("y", api.dimensions.nodeInnerBorder)
      .attr("width", api.dimensions.nodeInnerWidth)
      .attr("height", api.dimensions.nodeInnerHeight)
      .append("image")
      .attr("image-rendering", api.settings.imageRendering)
      .attr("xlink:href", function(d) {
        return d.imageUrl;
      })
      .attr("x", 0)
      .attr("y", 0)
      .attr("width",api.dimensions.nodeInnerWidth)
      .attr("height", api.dimensions.nodeInnerHeight)
      .style("filter", function(pNode) {
        return pNode.selected ? "" : "url(#desaturate)";
      });
  };

  api.initializeNodes = function initializeNodes(nodes, nodesByName) {
    nodes
      .enter()
      .append("g")
      .attr("class", "node")
      .attr("id", function(pNode, c) {
        nodesByName[pNode.name] = d3.select(this);
        return c;
      })
      .on("click", function(pNode) {
        return api.clickHandler(pNode, nodesByName);
      })
      // Transition nodes to their new position.
      .attr("transform", function(d) {
        return "translate(" + d.x + "," + d.y + ")";
      });
    api.loadAcheivments(nodes, nodesByName);
    initializeBorder(nodes);
    initializeImages(nodes);
  };

  api.updateNode = function updateNode(node) {
    node
      .transition()
      .duration(api.durations.activateNode)
      .select("rect")
      .style("fill", api.settings.itemFill)
      .style("stroke", api.settings.strokeColor);
    node.select("image").style("filter", "");
  };
  return api;
})(techTree || {});



//Link Helper
var techTree = (function(api) {
  var linksBySource = {};

  api.initializeLinks = function initializeLinks(links) {
    links
      .enter()
      .insert("path", "g")
      .attr("class", "link")
      .attr("id", function(pLink) {
        linksBySource[pLink.source.name] =
          linksBySource[pLink.source.name] || [];
        linksBySource[pLink.source.name].push(d3.select(this));
        return pLink.source.name + "-" + pLink.target.name;
      })
      .attr("d", api.lineFunction)
      .style("stroke", function(pLink) {
        return pLink.source.selected
          ? "#d4c9ca" //grey will a little red
          : api.settings.initialLinkColor;
      });
  };
  api.updateLinks = function updateLinks(node, nodesByName) {
    var links = linksBySource[node.name];
    if (links && links.length) {
      for (var i = 0; i < links.length; i += 1) {
        links[i]
          .transition()
          .duration(api.durations.activateLink)
          .style("stroke", api.settings.strokeColor);

        var d = links[i].datum().target;
        var name = d.name;
        var selectable = true;

        if (d.requirements) {
          for (var j = 0; j < d.requirements.length; j += 1) {
            var other = nodesByName[d.requirements[j]].datum();
            selectable = selectable && other.selected;
            d3.transition()
              .duration(api.durations.activateLink)
              .select("#" + other.name + "-" + d.name)
              .style("stroke", other.selected ? api.settings.strokeColor : "#ccc");
          }
        }
        if (selectable) {
          nodesByName[name]
            .transition()
            .duration(api.durations.activateLink)
            .select("rect")
            .style("stroke", api.settings.strokeColor);
        }
      }
    }
  };
  return api;
})(techTree || {});


//Image Utils
var techTree = (function(api) {
  api.setupImageUtils = function setupImageUtils(container) {
    container
      .append("filter")
      .attr("id", "desaturate")
      .append("feColorMatrix")
      .attr("type", "matrix")
      .attr(
        "values",
        "0.3333 0.3333 0.3333 0 0 0.3333 0.3333 0.3333 0 0 0.3333 0.3333 0.3333 0 0 0 0 0 1 0"
      );

    var shadow = container
      .append("filter")
      .attr("id", "dropshadow")
      .attr("filterUnits", "userSpaceOnUse")
      .attr("color-interpolation-filters", "sRGB")
      .attr("height", "130%")
      .append("feGaussianBlur")
      .attr("stdDeviation", "2")
      .append("feComponentTransfer")
      .attr("in", "SourceAlpha")
      .append("feFuncR")
      .attr("type", "discrete")
      .attr("tableValues", "0");
    
    shadow
      .append("feOffset")
      .attr("dx", "2")
      .attr("dy", "2")
      .attr("result", "offsetblur")
      .append("feMerge")
      .append("feMergeNode")
      .attr("in", "SourceGraphics");
 
  };
  return api;
})(techTree || {});


//json Loader
var techTree = (function(api) {
  api.createTreeFromJSON = function(config) {
    d3.json(config.dataFileName, function(error, json) {
        if (error) {
          return console.warn(error);
        }
        d3.json(config.settingsFileName, function(error, settings) {
          if (error) {
            return console.warn(error);
          }
          techTree.createTree(json.nodes, settings);
        });
    });
  };

  return api;
})(techTree || {});

