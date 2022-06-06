import { useBlockProps } from '@wordpress/block-editor';

import ProductCard from './ProductCard';
import { AttributesType } from './propTypes';

const Save = ({ attributes, publicDomain }) => {
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps.save()}>
      {attributes?.productGuid
        && (
        <ProductCard
          backgroundColor={attributes.backgroundColor}
          cardRadius={attributes.cardRadius}
          cardShadow={attributes.cardShadow}
          displayDescription={attributes.displayDescription}
          displayImage={attributes.displayImage}
          isAmp={attributes.isAmp}
          linkColor={attributes.linkColor}
          productGuid={attributes.productGuid}
          publicDomain={publicDomain}
          textColor={attributes.textColor}
        />
        )}
    </div>
  );
};

Save.propTypes = {
  attributes: AttributesType.isRequired,
  publicDomain: AttributesType.isRequired,
};

export default Save;
